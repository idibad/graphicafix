/**
 * Graphicafix PWA Registration & Android Chrome Install Prompt Controller
 */
(function () {
  if (typeof window === 'undefined') return;

  const DISMISS_KEY = 'gfx_pwa_banner_dismissed_at';
  const DISMISS_DAYS = 7;
  let deferredPrompt = null;

  function isStandalone() {
    return (
      (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) ||
      window.navigator.standalone === true
    );
  }

  function resolveBasePath() {
    if (window.GFX_PWA_BASE) {
      return window.GFX_PWA_BASE.replace(/\/?$/, '/');
    }
    const manifestLink = document.querySelector('link[rel="manifest"]');
    if (manifestLink && manifestLink.href) {
      try {
        const u = new URL(manifestLink.href, window.location.href);
        return u.pathname.replace(/manifest\.json.*$/i, '');
      } catch (e) {}
    }
    return '/';
  }

  // 1. Register Root Service Worker (required for Chrome Android WebAPK installability)
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      const base = resolveBasePath();
      const swUrl = base + 'sw.js';
      navigator.serviceWorker
        .register(swUrl, { scope: base })
        .catch(function (err) {
          console.warn('[PWA] SW registration skipped:', err && err.message ? err.message : err);
        });
    });
  }

  function toggleInstallTriggers(show) {
    document.querySelectorAll('[data-pwa-install]').forEach(function (el) {
      el.style.display = show ? (el.getAttribute('data-pwa-display') || 'inline-flex') : 'none';
    });
  }

  function wasRecentlyDismissed() {
    try {
      const ts = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
      if (!ts) return false;
      return (Date.now() - ts) < (DISMISS_DAYS * 86400000);
    } catch (e) {
      return false;
    }
  }

  function markDismissed() {
    try {
      localStorage.setItem(DISMISS_KEY, String(Date.now()));
    } catch (e) {}
  }

  function removeFloatingBanner() {
    const existing = document.getElementById('gfxPwaInstallBanner');
    if (existing) existing.remove();
  }

  function showFloatingBanner() {
    if (isStandalone() || wasRecentlyDismissed() || document.getElementById('gfxPwaInstallBanner')) {
      return;
    }
    const base = resolveBasePath();
    const banner = document.createElement('div');
    banner.id = 'gfxPwaInstallBanner';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-label', 'Install Graphicafix App');
    banner.style.cssText = [
      'position:fixed',
      'bottom:18px',
      'left:50%',
      'transform:translateX(-50%)',
      'width:calc(100vw - 28px)',
      'max-width:420px',
      'background:#ffffff',
      'border:1.5px solid #d4f48c',
      'border-left:4px solid #b8f35a',
      'border-radius:16px',
      'padding:12px 14px',
      'box-shadow:0 14px 36px rgba(15,23,42,0.16)',
      'display:flex',
      'align-items:center',
      'gap:12px',
      'z-index:10060',
      'font-family:inherit'
    ].join(';');

    banner.innerHTML =
      '<img src="' + base + 'assets/images/pwa/icon-192.png" alt="Graphicafix" style="width:42px;height:42px;border-radius:11px;flex-shrink:0;">' +
      '<div style="flex:1;min-width:0;">' +
        '<div style="font-size:13.5px;font-weight:800;color:#0f172a;line-height:1.2;">Install Graphicafix App</div>' +
        '<div style="font-size:11.5px;color:#64748b;margin-top:2px;line-height:1.3;">Quick access to Tasks, Attendance &amp; Dashboard right from your home screen.</div>' +
      '</div>' +
      '<button type="button" id="gfxPwaBannerInstallBtn" style="background:#b8f35a;color:#142600;border:1px solid #a3e635;border-radius:10px;padding:8px 13px;font-size:12px;font-weight:800;cursor:pointer;white-space:nowrap;flex-shrink:0;">Install</button>' +
      '<button type="button" id="gfxPwaBannerCloseBtn" aria-label="Dismiss" style="background:none;border:none;color:#94a3b8;font-size:18px;line-height:1;padding:2px 4px;cursor:pointer;flex-shrink:0;">&times;</button>';

    document.body.appendChild(banner);

    const installBtn = document.getElementById('gfxPwaBannerInstallBtn');
    const closeBtn = document.getElementById('gfxPwaBannerCloseBtn');
    if (installBtn) {
      installBtn.addEventListener('click', function () {
        window.triggerGfxPwaInstall();
      });
    }
    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        markDismissed();
        removeFloatingBanner();
      });
    }
  }

  window.triggerGfxPwaInstall = async function () {
    if (!deferredPrompt) return false;
    try {
      deferredPrompt.prompt();
      const choice = await deferredPrompt.userChoice;
      deferredPrompt = null;
      if (choice && choice.outcome === 'accepted') {
        toggleInstallTriggers(false);
        removeFloatingBanner();
        return true;
      }
    } catch (e) {
      console.warn('[PWA] Install prompt error:', e);
    }
    return false;
  };

  // 2. Capture Chrome Android / Desktop install prompt
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    if (!isStandalone()) {
      toggleInstallTriggers(true);
      // Show subtle install prompt on mobile/tablet or login page
      if (window.innerWidth <= 900 || window.location.pathname.includes('login')) {
        setTimeout(showFloatingBanner, 1200);
      }
    }
  });

  window.addEventListener('appinstalled', function () {
    deferredPrompt = null;
    toggleInstallTriggers(false);
    removeFloatingBanner();
  });

  // Hide triggers if already running inside installed standalone app
  if (isStandalone()) {
    document.addEventListener('DOMContentLoaded', function () {
      toggleInstallTriggers(false);
      removeFloatingBanner();
    });
  }

  // 3. Offline / Reconnected Banner for Cached Pages
  function removeOfflineBanner() {
    const el = document.getElementById('gfxOfflineStatusPill');
    if (el) el.remove();
  }

  function showOfflineStatus(isOffline) {
    // Defer to offline-sync.js unified sync pill when loaded on dashboard pages
    if (window.GFX_OFFLINE_SYNC) {
      removeOfflineBanner();
      return;
    }
    removeOfflineBanner();
    const pill = document.createElement('div');
    pill.id = 'gfxOfflineStatusPill';
    pill.setAttribute('role', 'status');
    pill.style.cssText = [
      'position:fixed',
      'bottom:16px',
      'left:50%',
      'transform:translateX(-50%)',
      'z-index:10070',
      'display:inline-flex',
      'align-items:center',
      'gap:10px',
      'padding:9px 14px',
      'border-radius:999px',
      'font-family:inherit',
      'font-size:12.5px',
      'font-weight:700',
      'box-shadow:0 10px 28px rgba(15,23,42,0.22)',
      'max-width:calc(100vw - 24px)',
      'white-space:nowrap',
      isOffline
        ? 'background:#0f172a;color:#f8fafc;border:1px solid rgba(184,243,90,0.4)'
        : 'background:#ecfccb;color:#142600;border:1px solid #a3e635'
    ].join(';');

    if (isOffline) {
      pill.innerHTML =
        '<span style="width:8px;height:8px;border-radius:50%;background:#f59e0b;flex-shrink:0;"></span>' +
        '<span>Offline &middot; Viewing cached version</span>' +
        '<button type="button" onclick="window.location.reload()" style="background:#b8f35a;color:#142600;border:none;border-radius:999px;padding:4px 10px;font-size:11.5px;font-weight:800;cursor:pointer;">Retry</button>';
      document.body.appendChild(pill);
    } else {
      pill.innerHTML =
        '<span style="width:8px;height:8px;border-radius:50%;background:#16a34a;flex-shrink:0;"></span>' +
        '<span>Back online!</span>' +
        '<button type="button" onclick="window.location.reload()" style="background:#142600;color:#b8f35a;border:none;border-radius:999px;padding:4px 10px;font-size:11.5px;font-weight:800;cursor:pointer;">Refresh</button>';
      document.body.appendChild(pill);
      setTimeout(removeOfflineBanner, 5000);
    }
  }

  window.addEventListener('offline', function () {
    showOfflineStatus(true);
  });
  window.addEventListener('online', function () {
    showOfflineStatus(false);
  });
  document.addEventListener('DOMContentLoaded', function () {
    if (navigator.onLine === false) {
      showOfflineStatus(true);
    }
  });
})();
