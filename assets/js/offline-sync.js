/**
 * Graphicafix Hybrid Offline Sync Queue + Read-Only Form Guard
 *
 * - Auto-queues & auto-syncs everyday field/team writes when offline:
 *   Tasks (status, create, edit, comments, subtasks, members, extensions, stages),
 *   Client Field Visits (create, update, follow-ups), and DPR actions.
 * - Keeps sensitive / financial / GPS actions (Attendance Check-In/Out, Invoices,
 *   Quotations, Salaries, Team/User Admin, File Uploads) online-only with a friendly
 *   guard that preserves form inputs and never loses user data.
 * - Pauses safely if the login session expired while offline and resumes automatically
 *   after signing back in.
 */
(function () {
  if (typeof window === 'undefined') return;

  const DB_NAME = 'gfx_offline_sync_db';
  const DB_VERSION = 1;
  const STORE_NAME = 'sync_queue';
  const NATIVE_FETCH = window.fetch ? window.fetch.bind(window) : null;

  let isSyncing = false;
  let authPaused = false;
  let dbPromise = null;
  let drawerOpen = false;

  // ── 1. Endpoint Classification ──────────────────────────────────────────────
  const QUEUEABLE_TASK_ACTIONS = new Set([
    'update_status',
    'create_task',
    'edit_task',
    'add_comment',
    'add_subtask',
    'toggle_subtask',
    'delete_subtask',
    'update_task_assignees',
    'request_extension',
    'advance_stage'
  ]);

  const QUEUEABLE_VISIT_ACTIONS = new Set([
    'create',
    'add_visit',
    'save_visit',
    'update',
    'update_visit',
    'update_status',
    'add_followup',
    'log_followup'
  ]);

  const QUEUEABLE_DPR_ACTIONS = new Set([
    'save_dpr',
    'submit_dpr',
    'update_dpr',
    'add_entry',
    'update_entry'
  ]);

  const SENSITIVE_PATTERNS = [
    /attendance_api/i,
    /invoice/i,
    /quotation/i,
    /salar/i,
    /payroll/i,
    /manage_users/i,
    /user_action/i,
    /manage_team/i,
    /logout/i,
    /login/i,
    /password/i,
    /delete_/i
  ];

  function parseUrlObj(input) {
    try {
      const raw = typeof input === 'string' ? input : (input && input.url ? input.url : '');
      return new URL(raw, window.location.href);
    } catch (e) {
      return null;
    }
  }

  function isSensitiveEndpoint(urlObj) {
    if (!urlObj) return true;
    return SENSITIVE_PATTERNS.some((rx) => rx.test(urlObj.pathname + urlObj.search));
  }

  function serializeBody(body) {
    if (!body) return { bodyType: 'empty', entries: [], hasFile: false, map: {} };

    if (typeof FormData !== 'undefined' && body instanceof FormData) {
      const entries = [];
      const map = {};
      let hasFile = false;
      body.forEach(function (val, key) {
        if (typeof File !== 'undefined' && val instanceof File) {
          if (val.size > 0 || (val.name && val.name.trim() !== '')) {
            hasFile = true;
          }
          return;
        }
        const s = String(val);
        entries.push([key, s]);
        map[key] = s;
      });
      return { bodyType: 'formdata', entries: entries, hasFile: hasFile, map: map };
    }

    if (typeof URLSearchParams !== 'undefined' && body instanceof URLSearchParams) {
      const entries = [];
      const map = {};
      body.forEach(function (val, key) {
        entries.push([key, String(val)]);
        map[key] = String(val);
      });
      return { bodyType: 'urlencoded', entries: entries, hasFile: false, map: map };
    }

    if (typeof body === 'string') {
      const trimmed = body.trim();
      if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
        let parsed = {};
        try { parsed = JSON.parse(trimmed); } catch (e) {}
        return { bodyType: 'json', rawBody: body, entries: [], hasFile: false, map: parsed || {} };
      }
      const params = new URLSearchParams(body);
      const entries = [];
      const map = {};
      params.forEach(function (val, key) {
        entries.push([key, val]);
        map[key] = val;
      });
      return { bodyType: 'urlencoded', entries: entries, hasFile: false, map: map };
    }

    return { bodyType: 'unknown', entries: [], hasFile: false, map: {} };
  }

  function classifyRequest(urlObj, method, serialized) {
    if (!urlObj || urlObj.origin !== window.location.origin) {
      return { queueable: false, sensitive: false };
    }
    if ((method || 'GET').toUpperCase() !== 'POST') {
      return { queueable: false, sensitive: false };
    }

    const p = urlObj.pathname.toLowerCase();
    const map = serialized.map || {};
    const urlAction = (urlObj.searchParams.get('action') || '').toLowerCase();
    const bodyAction = String(map.ajax_action || map.action || urlAction || '').toLowerCase();

    if (isSensitiveEndpoint(urlObj)) {
      return { queueable: false, sensitive: true, label: 'Sensitive action' };
    }

    // 1. Task Actions (task_action.php or tasks.php AJAX)
    if (p.endsWith('/task_action.php') || p.endsWith('/task_action') || p.endsWith('/tasks.php') || p.endsWith('/tasks')) {
      if (QUEUEABLE_TASK_ACTIONS.has(bodyAction)) {
        const taskLabels = {
          update_status: 'Task status update',
          create_task: 'New task: ' + (map.title || 'Untitled'),
          edit_task: 'Edit task #' + (map.task_id || ''),
          add_comment: 'Task comment',
          add_subtask: 'New subtask',
          toggle_subtask: 'Subtask update',
          delete_subtask: 'Remove subtask',
          update_task_assignees: 'Update task members',
          request_extension: 'Extension request',
          advance_stage: 'Advance task stage'
        };
        const dedupeKey = bodyAction === 'update_status' && map.task_id
          ? 'task_status:' + map.task_id
          : null;
        return {
          queueable: true,
          sensitive: false,
          label: taskLabels[bodyAction] || 'Task update',
          dedupeKey: dedupeKey
        };
      }
      return { queueable: false, sensitive: false };
    }

    // 2. Dashboard Quick Task Checkbox (update_task_status.php)
    if (p.endsWith('/update_task_status.php') || p.endsWith('/update_task_status')) {
      return {
        queueable: true,
        sensitive: false,
        label: 'Task status update' + (map.id ? ' #' + map.id : ''),
        dedupeKey: map.id ? 'task_status:' + map.id : null
      };
    }

    // 3. Sales Field Visits API (sales_visits_api.php)
    if (p.endsWith('/sales_visits_api.php') || p.endsWith('/sales_visits_api')) {
      if (bodyAction && (QUEUEABLE_VISIT_ACTIONS.has(bodyAction) || bodyAction.includes('visit') || bodyAction.includes('followup'))) {
        if (bodyAction.startsWith('fetch') || bodyAction.startsWith('get')) {
          return { queueable: false, sensitive: false };
        }
        return {
          queueable: true,
          sensitive: false,
          label: 'Client field visit' + (map.company_name ? ': ' + map.company_name : ' update'),
          dedupeKey: null
        };
      }
      return { queueable: false, sensitive: false };
    }

    // 4. Daily Progress Report Actions (dpr_action.php)
    if (p.endsWith('/dpr_action.php') || p.endsWith('/dpr_action')) {
      if (!bodyAction || QUEUEABLE_DPR_ACTIONS.has(bodyAction) || (!bodyAction.startsWith('get') && !bodyAction.startsWith('fetch') && !bodyAction.startsWith('delete'))) {
        return {
          queueable: true,
          sensitive: false,
          label: 'Daily Progress Report update',
          dedupeKey: null
        };
      }
    }

    return { queueable: false, sensitive: true };
  }

  // ── 2. IndexedDB Queue Storage ──────────────────────────────────────────────
  function openDB() {
    if (dbPromise) return dbPromise;
    dbPromise = new Promise(function (resolve, reject) {
      if (!('indexedDB' in window)) {
        reject(new Error('IndexedDB unavailable'));
        return;
      }
      const req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = function (e) {
        const db = e.target.result;
        if (!db.objectStoreNames.contains(STORE_NAME)) {
          db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
        }
      };
      req.onsuccess = function () { resolve(req.result); };
      req.onerror = function () { reject(req.error); };
    });
    return dbPromise;
  }

  async function getAllQueued() {
    try {
      const db = await openDB();
      return await new Promise(function (resolve, reject) {
        const tx = db.transaction(STORE_NAME, 'readonly');
        const req = tx.objectStore(STORE_NAME).getAll();
        req.onsuccess = function () {
          const items = (req.result || []).sort((a, b) => (a.createdAt || 0) - (b.createdAt || 0));
          resolve(items);
        };
        req.onerror = function () { reject(req.error); };
      });
    } catch (e) {
      return [];
    }
  }

  async function addQueuedItem(item) {
    const db = await openDB();
    // Deduplicate same task status updates so only the latest target status is replayed
    if (item.dedupeKey) {
      const existing = await getAllQueued();
      for (const ex of existing) {
        if (ex.dedupeKey === item.dedupeKey) {
          await deleteQueuedItem(ex.id);
        }
      }
    }
    return await new Promise(function (resolve, reject) {
      const tx = db.transaction(STORE_NAME, 'readwrite');
      const req = tx.objectStore(STORE_NAME).add(item);
      req.onsuccess = function () { resolve(req.result); };
      req.onerror = function () { reject(req.error); };
    });
  }

  async function deleteQueuedItem(id) {
    try {
      const db = await openDB();
      await new Promise(function (resolve, reject) {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        const req = tx.objectStore(STORE_NAME).delete(id);
        req.onsuccess = function () { resolve(); };
        req.onerror = function () { reject(req.error); };
      });
    } catch (e) {}
  }

  async function clearAllQueued() {
    try {
      const db = await openDB();
      await new Promise(function (resolve, reject) {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        const req = tx.objectStore(STORE_NAME).clear();
        req.onsuccess = function () { resolve(); };
        req.onerror = function () { reject(req.error); };
      });
    } catch (e) {}
    authPaused = false;
    drawerOpen = false;
    await renderStatusUI();
  }

  // ── 3. Toast & Unified Offline / Sync Pill UI ───────────────────────────────
  function showSyncToast(message, tone) {
    if (!document.body) return;
    let shelf = document.getElementById('gfxOfflineToastShelf');
    if (!shelf) {
      shelf = document.createElement('div');
      shelf.id = 'gfxOfflineToastShelf';
      shelf.style.cssText = 'position:fixed;top:72px;right:18px;z-index:10080;display:flex;flex-direction:column;gap:8px;max-width:calc(100vw - 32px);pointer-events:none;';
      document.body.appendChild(shelf);
    }
    const toast = document.createElement('div');
    const bg = tone === 'error' ? '#fef2f2' : (tone === 'success' ? '#ecfccb' : '#0f172a');
    const fg = tone === 'error' ? '#991b1b' : (tone === 'success' ? '#142600' : '#f8fafc');
    const bd = tone === 'error' ? '#fecaca' : (tone === 'success' ? '#a3e635' : 'rgba(184,243,90,0.45)');
    toast.style.cssText = [
      'pointer-events:auto',
      'background:' + bg,
      'color:' + fg,
      'border:1px solid ' + bd,
      'padding:10px 14px',
      'border-radius:12px',
      'font-size:12.5px',
      'font-weight:700',
      'box-shadow:0 10px 26px rgba(15,23,42,0.16)',
      'display:flex',
      'align-items:center',
      'gap:8px',
      'transition:opacity 0.25s ease,transform 0.25s ease'
    ].join(';');
    const dotColor = tone === 'error' ? '#dc2626' : (tone === 'success' ? '#65a30d' : '#b8f35a');
    toast.innerHTML = '<span style="width:8px;height:8px;border-radius:50%;background:' + dotColor + ';flex-shrink:0;"></span><span>' + escapeHtml(message) + '</span>';
    shelf.appendChild(toast);
    setTimeout(function () {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(-6px)';
      setTimeout(function () { toast.remove(); }, 260);
    }, 3600);
  }

  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatRelTime(ts) {
    const secs = Math.max(1, Math.round((Date.now() - (ts || Date.now())) / 1000));
    if (secs < 60) return 'just now';
    const mins = Math.round(secs / 60);
    if (mins < 60) return mins + 'm ago';
    return Math.round(mins / 60) + 'h ago';
  }

  async function renderStatusUI() {
    if (!document.body) return;
    const items = await getAllQueued();
    const count = items.length;
    const offline = (navigator.onLine === false);

    // Remove legacy simple pill from pwa-install.js if present so there is only 1 unified pill
    const legacyPill = document.getElementById('gfxOfflineStatusPill');
    if (legacyPill) legacyPill.remove();

    let wrap = document.getElementById('gfxSyncStatusWrap');
    if (!offline && count === 0 && !isSyncing) {
      if (wrap) wrap.remove();
      return;
    }

    if (!wrap) {
      wrap = document.createElement('div');
      wrap.id = 'gfxSyncStatusWrap';
      wrap.style.cssText = 'position:fixed;bottom:16px;left:50%;transform:translateX(-50%);z-index:10075;display:flex;flex-direction:column;align-items:center;gap:8px;max-width:calc(100vw - 24px);font-family:inherit;';
      document.body.appendChild(wrap);
    }

    let drawerHtml = '';
    if (drawerOpen && count > 0) {
      const listHtml = items.map(function (it) {
        return '<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:7px 0;border-bottom:1px solid #f1f5f9;">' +
          '<div style="min-width:0;flex:1;">' +
            '<div style="font-size:12px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(it.label) + '</div>' +
            '<div style="font-size:10.5px;color:#64748b;">Queued ' + formatRelTime(it.createdAt) + '</div>' +
          '</div>' +
          '<button type="button" data-gfx-drop="' + it.id + '" style="background:none;border:none;color:#94a3b8;font-size:15px;cursor:pointer;padding:2px 6px;" title="Discard">&times;</button>' +
        '</div>';
      }).join('');

      drawerHtml =
        '<div style="width:340px;max-width:calc(100vw - 24px);background:#ffffff;border:1px solid #d4f48c;border-radius:14px;padding:12px 14px;box-shadow:0 14px 36px rgba(15,23,42,0.18);">' +
          '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">' +
            '<span style="font-size:12px;font-weight:800;color:#0f172a;">Pending Offline Changes (' + count + ')</span>' +
            '<button type="button" id="gfxSyncClearAll" style="background:none;border:none;color:#dc2626;font-size:11px;font-weight:700;cursor:pointer;">Clear All</button>' +
          '</div>' +
          '<div style="max-height:190px;overflow-y:auto;">' + listHtml + '</div>' +
          (!offline ? '<button type="button" id="gfxSyncNowDrawer" style="margin-top:10px;width:100%;padding:8px;border-radius:9px;background:#b8f35a;color:#142600;border:1px solid #a3e635;font-size:12px;font-weight:800;cursor:pointer;">Sync Now</button>' : '') +
        '</div>';
    }

    let dot = '#f59e0b';
    let text = 'Offline &middot; Viewing cached version';
    let btnHtml = '<button type="button" id="gfxSyncRetryBtn" style="background:#b8f35a;color:#142600;border:none;border-radius:999px;padding:4px 10px;font-size:11.5px;font-weight:800;cursor:pointer;">Retry</button>';

    if (isSyncing) {
      dot = '#b8f35a';
      text = 'Syncing ' + count + ' saved change' + (count === 1 ? '' : 's') + '...';
      btnHtml = '';
    } else if (authPaused && count > 0) {
      dot = '#ef4444';
      text = count + ' change' + (count === 1 ? '' : 's') + ' waiting &middot; Sign in to sync';
      btnHtml = '<a href="login.php" style="background:#b8f35a;color:#142600;text-decoration:none;border-radius:999px;padding:4px 10px;font-size:11.5px;font-weight:800;">Sign In</a>';
    } else if (offline && count > 0) {
      dot = '#f59e0b';
      text = 'Offline &middot; ' + count + ' saved to sync';
      btnHtml = '<button type="button" id="gfxSyncViewBtn" style="background:#b8f35a;color:#142600;border:none;border-radius:999px;padding:4px 10px;font-size:11.5px;font-weight:800;cursor:pointer;">' + (drawerOpen ? 'Hide' : 'View') + '</button>';
    } else if (!offline && count > 0) {
      dot = '#b8f35a';
      text = count + ' offline change' + (count === 1 ? '' : 's') + ' ready';
      btnHtml = '<button type="button" id="gfxSyncNowBtn" style="background:#b8f35a;color:#142600;border:none;border-radius:999px;padding:4px 10px;font-size:11.5px;font-weight:800;cursor:pointer;">Sync Now</button>';
    }

    wrap.innerHTML = drawerHtml +
      '<div style="display:inline-flex;align-items:center;gap:10px;padding:9px 14px;border-radius:999px;background:#0f172a;color:#f8fafc;border:1px solid rgba(184,243,90,0.45);font-size:12.5px;font-weight:700;box-shadow:0 10px 28px rgba(15,23,42,0.22);white-space:nowrap;">' +
        '<span style="width:8px;height:8px;border-radius:50%;background:' + dot + ';flex-shrink:0;"></span>' +
        '<span>' + text + '</span>' +
        btnHtml +
      '</div>';

    const retryBtn = document.getElementById('gfxSyncRetryBtn');
    if (retryBtn) retryBtn.addEventListener('click', function () { window.location.reload(); });
    const viewBtn = document.getElementById('gfxSyncViewBtn');
    if (viewBtn) viewBtn.addEventListener('click', function () { drawerOpen = !drawerOpen; renderStatusUI(); });
    const nowBtn = document.getElementById('gfxSyncNowBtn');
    if (nowBtn) nowBtn.addEventListener('click', function () { flushQueue(); });
    const nowDrawer = document.getElementById('gfxSyncNowDrawer');
    if (nowDrawer) nowDrawer.addEventListener('click', function () { flushQueue(); });
    const clearBtn = document.getElementById('gfxSyncClearAll');
    if (clearBtn) clearBtn.addEventListener('click', function () { clearAllQueued(); });
    wrap.querySelectorAll('[data-gfx-drop]').forEach(function (b) {
      b.addEventListener('click', async function () {
        await deleteQueuedItem(parseInt(b.getAttribute('data-gfx-drop'), 10));
        await renderStatusUI();
      });
    });
  }

  // ── 4. Replay Queue When Online ─────────────────────────────────────────────
  function rebuildBody(item) {
    if (item.bodyType === 'json') {
      return { body: item.rawBody || '{}', headers: { 'Content-Type': 'application/json' } };
    }
    if (item.bodyType === 'formdata') {
      const fd = new FormData();
      (item.entries || []).forEach(function (pair) { fd.append(pair[0], pair[1]); });
      return { body: fd, headers: {} };
    }
    const params = new URLSearchParams();
    (item.entries || []).forEach(function (pair) { params.append(pair[0], pair[1]); });
    return { body: params, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } };
  }

  async function flushQueue() {
    if (isSyncing || navigator.onLine === false || !NATIVE_FETCH) return;
    const items = await getAllQueued();
    if (items.length === 0) {
      authPaused = false;
      await renderStatusUI();
      return;
    }

    isSyncing = true;
    authPaused = false;
    await renderStatusUI();

    let syncedCount = 0;
    let hadRejected = false;

    for (const item of items) {
      if (navigator.onLine === false) break;
      try {
        const built = rebuildBody(item);
        const res = await NATIVE_FETCH(item.url, {
          method: item.method || 'POST',
          headers: built.headers,
          body: built.body,
          credentials: 'same-origin'
        });

        // Session expired redirect or 401/403 -> pause safely without dropping user work
        if (res.status === 401 || res.status === 403 || (res.redirected && /login/i.test(res.url))) {
          authPaused = true;
          break;
        }

        const text = await res.text();
        let data = null;
        try { data = JSON.parse(text); } catch (e) {}

        if (data && data.success === false) {
          const msg = String(data.message || '');
          if (/unauthorized|please log in|session expired/i.test(msg)) {
            authPaused = true;
            break;
          }
          // Server business-rule rejection (e.g. role restriction or deleted record): drop & inform user
          await deleteQueuedItem(item.id);
          hadRejected = true;
          showSyncToast('Could not sync "' + item.label + '": ' + (msg || 'Rejected by server'), 'error');
          continue;
        }

        if (res.ok) {
          await deleteQueuedItem(item.id);
          syncedCount++;
        } else {
          // Transient 5xx -> stop loop and retry next reconnect
          break;
        }
      } catch (err) {
        // Network dropped mid-sync -> stop cleanly and keep remaining items
        break;
      }
    }

    isSyncing = false;
    await renderStatusUI();

    if (authPaused) {
      showSyncToast('Your session expired while offline. Sign in to finish syncing saved changes.', 'error');
    } else if (syncedCount > 0) {
      showSyncToast('Synced ' + syncedCount + ' offline change' + (syncedCount === 1 ? '' : 's') + '!', 'success');
      // Refresh view smoothly if user is not currently typing in an open form/input
      const activeTag = (document.activeElement && document.activeElement.tagName || '').toLowerCase();
      const isTyping = (activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select');
      const openModal = document.querySelector('.modal.show, .modal-overlay.active');
      if (!isTyping && !openModal && !hadRejected) {
        setTimeout(function () { window.location.reload(); }, 1200);
      }
    }
  }

  // ── 5. Intercept window.fetch for Queueable Writes & Sensitive Offline Guard ─
  if (NATIVE_FETCH) {
    window.fetch = async function (input, init) {
      const opts = init || {};
      const method = (opts.method || (input && input.method) || 'GET').toUpperCase();
      const urlObj = parseUrlObj(input);

      if (method !== 'POST' || !urlObj || urlObj.origin !== window.location.origin) {
        return NATIVE_FETCH(input, init);
      }

      const serialized = serializeBody(opts.body);
      const info = classifyRequest(urlObj, method, serialized);

      // Block file uploads cleanly when offline
      if (serialized.hasFile && navigator.onLine === false) {
        showSyncToast('File uploads require an internet connection.', 'error');
        return new Response(
          JSON.stringify({ success: false, offline_blocked: true, message: 'File uploads require an internet connection.' }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }

      // Sensitive non-queueable POST while offline -> friendly guard
      if (!info.queueable && navigator.onLine === false) {
        showSyncToast('You are offline — reconnect to complete this action.', 'error');
        return new Response(
          JSON.stringify({ success: false, offline_blocked: true, message: 'You are offline. Please reconnect to complete this action.' }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }

      // Queueable write while already offline -> queue immediately
      if (info.queueable && !serialized.hasFile && navigator.onLine === false) {
        try {
          await addQueuedItem({
            url: urlObj.toString(),
            method: 'POST',
            bodyType: serialized.bodyType,
            entries: serialized.entries,
            rawBody: serialized.rawBody || null,
            label: info.label,
            dedupeKey: info.dedupeKey || null,
            createdAt: Date.now()
          });
          await renderStatusUI();
          showSyncToast('Saved offline — will sync automatically when reconnected.', 'info');
          return new Response(
            JSON.stringify({
              success: true,
              queued: true,
              message: 'Saved offline — will sync automatically when reconnected.'
            }),
            { status: 200, headers: { 'Content-Type': 'application/json' } }
          );
        } catch (e) {
          return NATIVE_FETCH(input, init);
        }
      }

      // Online attempt: if network fails mid-request on a queueable write, gracefully queue it!
      try {
        return await NATIVE_FETCH(input, init);
      } catch (netErr) {
        if (info.queueable && !serialized.hasFile) {
          try {
            await addQueuedItem({
              url: urlObj.toString(),
              method: 'POST',
              bodyType: serialized.bodyType,
              entries: serialized.entries,
              rawBody: serialized.rawBody || null,
              label: info.label,
              dedupeKey: info.dedupeKey || null,
              createdAt: Date.now()
            });
            await renderStatusUI();
            showSyncToast('Connection lost — saved offline & will sync when back online.', 'info');
            return new Response(
              JSON.stringify({
                success: true,
                queued: true,
                message: 'Saved offline — will sync automatically when reconnected.'
              }),
              { status: 200, headers: { 'Content-Type': 'application/json' } }
            );
          } catch (qErr) {}
        }
        throw netErr;
      }
    };
  }

  // ── 6. Hook jQuery $.ajax for legacy checkbox endpoints (e.g. update_task_status.php) ─
  function hookJQueryAjax() {
    if (!window.jQuery || !window.jQuery.ajaxPrefilter || window.__gfxJquerySyncHooked) return;
    window.__gfxJquerySyncHooked = true;
    window.jQuery.ajaxTransport('+*', function (options, originalOptions) {
      if ((options.type || 'GET').toUpperCase() !== 'POST') return;
      if (navigator.onLine !== false) return;

      const urlObj = parseUrlObj(options.url);
      const rawData = typeof originalOptions.data === 'object' && originalOptions.data !== null
        ? new URLSearchParams(originalOptions.data).toString()
        : (options.data || '');
      const serialized = serializeBody(rawData);
      const info = classifyRequest(urlObj, 'POST', serialized);

      if (!info.queueable) return;

      return {
        send: function (_headers, completeCallback) {
          addQueuedItem({
            url: urlObj.toString(),
            method: 'POST',
            bodyType: 'urlencoded',
            entries: serialized.entries,
            rawBody: null,
            label: info.label,
            dedupeKey: info.dedupeKey || null,
            createdAt: Date.now()
          }).then(async function () {
            await renderStatusUI();
            showSyncToast('Saved offline — will sync automatically when reconnected.', 'info');
            completeCallback(200, 'OK', {
              json: { success: true, queued: true, message: 'Saved offline — will sync when reconnected.' },
              text: JSON.stringify({ success: true, queued: true, message: 'Saved offline — will sync when reconnected.' })
            });
          }).catch(function () {
            completeCallback(0, 'error');
          });
        },
        abort: function () {}
      };
    });
  }

  // ── 7. Safe Read-Only Guard for Standard HTML POST Forms When Offline ───────
  // Bubble phase: only catches forms NOT already handled by an AJAX onsubmit listener
  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented || navigator.onLine !== false) return;
    const form = e.target;
    if (!form || form.tagName !== 'FORM') return;
    const method = (form.getAttribute('method') || 'GET').toUpperCase();
    if (method !== 'POST') return;

    e.preventDefault();
    showSyncToast('You are offline — your form inputs are preserved. Reconnect to submit.', 'error');
  }, false);

  // Clear queue cleanly on explicit logout clicks
  document.addEventListener('click', function (e) {
    const link = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (link && /logout(?:\.php)?$/i.test(link.getAttribute('href') || '')) {
      clearAllQueued();
    }
  }, true);

  // ── 8. Lifecycle Hooks & Public API ─────────────────────────────────────────
  window.GFX_OFFLINE_SYNC = {
    flush: flushQueue,
    getAll: getAllQueued,
    clear: clearAllQueued,
    render: renderStatusUI
  };

  window.addEventListener('online', function () {
    flushQueue();
  });

  window.addEventListener('offline', function () {
    renderStatusUI();
  });

  document.addEventListener('DOMContentLoaded', function () {
    hookJQueryAjax();
    renderStatusUI();
    if (navigator.onLine !== false) {
      flushQueue();
    }
  });

  window.addEventListener('load', function () {
    hookJQueryAjax();
  });
})();
