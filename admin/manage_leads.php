<?php
// Prevent PHP from crashing the page visually
error_reporting(0);
ini_set('display_errors', '0');

include 'dashboard_header.php';

// ── Auto-create tables & Apply New CRM Columns ────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL, email VARCHAR(200), phone VARCHAR(50), company VARCHAR(150),
    source ENUM('website','referral','social_media','cold_outreach','walk_in','project_request','contact_form','other') DEFAULT 'other',
    service_interest VARCHAR(200), budget VARCHAR(100),
    pipeline_stage ENUM('new','contacted','qualified','proposal_sent','followup_pending','negotiation','won','lost') DEFAULT 'new',
    priority ENUM('low','medium','high') DEFAULT 'medium',
    assigned_to INT DEFAULT NULL, notes TEXT,
    deal_value DECIMAL(12,2) DEFAULT 0.00, expected_close DATE DEFAULT NULL,
    lost_reason VARCHAR(255) DEFAULT NULL, converted_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT NOW(), updated_at DATETIME DEFAULT NOW() ON UPDATE NOW()
)");

// Safely patch the table for the new intelligent CRM columns
$cols = array_column($conn->query("SHOW COLUMNS FROM leads")->fetch_all(MYSQLI_ASSOC), 'Field');
if(!in_array('next_action', $cols)) $conn->query("ALTER TABLE leads ADD COLUMN next_action VARCHAR(255) DEFAULT NULL");
if(!in_array('next_action_date', $cols)) $conn->query("ALTER TABLE leads ADD COLUMN next_action_date DATETIME DEFAULT NULL");
if(!in_array('lead_score', $cols)) $conn->query("ALTER TABLE leads ADD COLUMN lead_score INT DEFAULT 0");
if(!in_array('waiting_on', $cols)) $conn->query("ALTER TABLE leads ADD COLUMN waiting_on ENUM('client','you','none') DEFAULT 'none'");
if(!in_array('last_contacted', $cols)) $conn->query("ALTER TABLE leads ADD COLUMN last_contacted DATETIME DEFAULT NULL");

// Safely update pipeline_stage enum to include followup_pending
$conn->query("ALTER TABLE leads MODIFY COLUMN pipeline_stage ENUM('new','contacted','qualified','proposal_sent','followup_pending','negotiation','won','lost') DEFAULT 'new'");

// ── Helpers ───────────────────────────────────────────────────────────────────
$stage_meta = [
    'new'              => ['label'=>'New',              'color'=>'#0284c7','bg'=>'#e0f2fe','emoji'=>'🆕'],
    'contacted'        => ['label'=>'Contacted',        'color'=>'#7c3aed','bg'=>'#ede9fe','emoji'=>'<i class="fas fa-phone"></i>'],
    'qualified'        => ['label'=>'Qualified',        'color'=>'#a16207','bg'=>'#fef9c3','emoji'=>'<i class="fas fa-check-circle"></i>'],
    'proposal_sent'    => ['label'=>'Proposal Sent',    'color'=>'#b45309','bg'=>'#fff7ed','emoji'=>'<i class="fas fa-file-alt"></i>'],
    'followup_pending' => ['label'=>'Follow-up',        'color'=>'#eab308','bg'=>'#fef08a','emoji'=>'⏳'],
    'negotiation'      => ['label'=>'Negotiation',      'color'=>'#0369a1','bg'=>'#e0f2fe','emoji'=>'<i class="fas fa-handshake"></i>'],
    'won'              => ['label'=>'Won',              'color'=>'#2b7a2b','bg'=>'#d7f8b8','emoji'=>'🏆'],
    'lost'             => ['label'=>'Lost',             'color'=>'#d63031','bg'=>'#ffe5e5','emoji'=>'<i class="fas fa-times-circle"></i>'],
];
$source_labels = ['website'=>'Website','referral'=>'Referral','social_media'=>'Social Media','cold_outreach'=>'Cold Outreach','walk_in'=>'Walk-in','project_request'=>'Project Request','contact_form'=>'Contact Form','other'=>'Other'];

function leadAvatar($n){$c=['pink','blue','green','orange','purple'];return $c[ord(strtoupper($n[0]??'A'))%5];}
function timeAgo($d){if(!$d)return 'Never';$s=time()-strtotime($d);if($s<60)return "Just now";if($s<3600)return round($s/60)."m ago";if($s<86400)return round($s/3600)."h ago";if($s<604800)return round($s/86400)."d ago";return date("M j, Y",strtotime($d));}
function cleanPhoneWa($p){return preg_replace('/[^0-9]/', '', $p);}
function cleanPost($k){return trim(htmlspecialchars($_POST[$k]??'',ENT_QUOTES,'UTF-8'));}

$uid = $_SESSION['user_id'] ?? null;

// ══════════════════════════════════════════════════════════════════════════════
//  HANDLE DRAG & DROP AJAX REQUESTS
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'update_stage') {
    header('Content-Type: application/json');
    $lid   = intval($_POST['lead_id'] ?? 0);
    $stage = cleanPost('new_stage');
    
    if ($lid && array_key_exists($stage, $stage_meta)) {
        $old = $conn->query("SELECT pipeline_stage FROM leads WHERE id=$lid")->fetch_assoc();
        
        if ($old && $old['pipeline_stage'] !== $stage) {
            // Auto-logic based on drop stage
            $extraSql = "";
            if ($stage === 'won') { $extraSql = ", converted_at=NOW(), waiting_on='none'"; }
            if ($stage === 'proposal_sent') { $extraSql = ", waiting_on='client', next_action='Follow up on proposal', next_action_date=DATE_ADD(NOW(), INTERVAL 2 DAY)"; }
            if ($stage === 'negotiation') { $extraSql = ", waiting_on='client', next_action='Finalize terms', next_action_date=DATE_ADD(NOW(), INTERVAL 1 DAY)"; }
            
            $conn->query("UPDATE leads SET pipeline_stage='$stage' $extraSql WHERE id=$lid");
            echo json_encode(['success' => true]);
            exit;
        }
    }
    echo json_encode(['success' => false]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
//  HANDLE QUICK ACTIONS (Speed = Sales)
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_POST['quick_action'])) {
    $lid    = intval($_POST['lead_id'] ?? 0);
    $action = $_POST['quick_action'];
    $back   = urlencode($_POST['current_tab'] ?? 'pipeline');

    if ($lid) {
        if ($action === 'followup_tomorrow') {
            $conn->query("UPDATE leads SET next_action='Follow-up', next_action_date=DATE_ADD(NOW(), INTERVAL 1 DAY) WHERE id=$lid");
        } elseif ($action === 'mark_contacted') {
            $conn->query("UPDATE leads SET pipeline_stage='contacted', last_contacted=NOW(), next_action='Qualify Lead', next_action_date=DATE_ADD(NOW(), INTERVAL 1 DAY), waiting_on='client' WHERE id=$lid");
        } elseif ($action === 'send_proposal') {
            $conn->query("UPDATE leads SET pipeline_stage='proposal_sent', last_contacted=NOW(), next_action='Follow up on proposal', next_action_date=DATE_ADD(NOW(), INTERVAL 2 DAY), waiting_on='client' WHERE id=$lid");
        } elseif ($action === 'mark_won') {
            $conn->query("UPDATE leads SET pipeline_stage='won', converted_at=NOW(), waiting_on='none', next_action=NULL, next_action_date=NULL WHERE id=$lid");
        }
    }
    header("Location: manage_leads.php?tab=$back&action_success=1");
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
//  POST ACTIONS (Forms)
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD']==='POST' && !isset($_POST['ajax_action']) && !isset($_POST['quick_action'])) {

    if (isset($_POST['add_lead']) || isset($_POST['edit_lead'])) {
        $lid     = intval($_POST['lead_id'] ?? 0);
        $name    = cleanPost('l_name');   $email = cleanPost('l_email');
        $phone   = cleanPost('l_phone');  $co    = cleanPost('l_company');
        $src     = cleanPost('l_source'); $svc   = cleanPost('l_service');
        $bgt     = cleanPost('l_budget'); $stage = cleanPost('l_stage') ?: 'new';
        $pri     = cleanPost('l_priority') ?: 'medium';
        $notes   = cleanPost('l_notes');
        $val     = floatval($_POST['l_deal_value'] ?? 0);
        $close   = !empty($_POST['l_expected_close']) ? $_POST['l_expected_close'] : null;
        $ass     = intval($_POST['l_assigned'] ?? 0) ?: null;
        $lost_r  = cleanPost('l_lost_reason');
        
        $n_act   = cleanPost('l_next_action');
        $n_date  = !empty($_POST['l_next_action_date']) ? $_POST['l_next_action_date'] : null;
        $wait    = cleanPost('l_waiting_on') ?: 'none';
        
        // Auto-logic on new
        if (isset($_POST['add_lead']) && empty($n_act)) {
            $n_act = "Initial Outreach";
            $n_date = date('Y-m-d H:i:s', strtotime('+1 day'));
            $wait = 'you';
        }

        if (isset($_POST['add_lead']) && $name) {
            $s=$conn->prepare("INSERT INTO leads (name,email,phone,company,source,service_interest,budget,pipeline_stage,priority,notes,deal_value,expected_close,assigned_to,next_action,next_action_date,waiting_on) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $s->bind_param("ssssssssssdsisss",$name,$email,$phone,$co,$src,$svc,$bgt,$stage,$pri,$notes,$val,$close,$ass,$n_act,$n_date,$wait);
            $s->execute();
        } elseif (isset($_POST['edit_lead']) && $lid && $name) {
            $s=$conn->prepare("UPDATE leads SET name=?,email=?,phone=?,company=?,source=?,service_interest=?,budget=?,pipeline_stage=?,priority=?,notes=?,deal_value=?,expected_close=?,assigned_to=?,lost_reason=?,next_action=?,next_action_date=?,waiting_on=? WHERE id=?");
            $s->bind_param("ssssssssssdsissssi",$name,$email,$phone,$co,$src,$svc,$bgt,$stage,$pri,$notes,$val,$close,$ass,$lost_r,$n_act,$n_date,$wait,$lid);
            $s->execute();
        }
        header('Location: manage_leads.php?saved=1'); exit;
    }

    if (isset($_POST['delete_lead'])) {
        $lid=intval($_POST['lead_id']??0);
        if ($lid) { $s=$conn->prepare("DELETE FROM leads WHERE id=?"); $s->bind_param("i",$lid); $s->execute(); }
        header('Location: manage_leads.php?deleted=1'); exit;
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  FETCH STATE & DYNAMIC SCORING
// ══════════════════════════════════════════════════════════════════════════════
$tab = $_GET['tab'] ?? 'pipeline';
if (!in_array($tab,['pipeline','list','analytics'])) $tab='pipeline';

$search = trim($_GET['q'] ?? '');
$where  = "WHERE 1=1";
$params = []; $types = '';
if ($search) {
    $like="%$search%";
    $where.=" AND (name LIKE ? OR email LIKE ? OR company LIKE ? OR phone LIKE ?)";
    $params[]=$like;$params[]=$like;$params[]=$like;$params[]=$like;$types.='ssss';
}
$lstmt=$conn->prepare("SELECT * FROM leads $where ORDER BY created_at DESC");
if ($types) $lstmt->bind_param($types,...$params);
$lstmt->execute();
$leads_result=$lstmt->get_result();

$leads=[];
$missing_action_count = 0;
$due_today_count = 0;
$total_won_val = 0;
$total_leads_val = 0;
$won_count = 0;

$source_stats = [];
$lost_reasons = [];

while($r = $leads_result->fetch_assoc()) {
    $total_leads_val++;
    if($r['pipeline_stage'] === 'won') { $won_count++; $total_won_val += $r['deal_value']; }
    if($r['pipeline_stage'] === 'lost' && $r['lost_reason']) { $lost_reasons[$r['lost_reason']] = ($lost_reasons[$r['lost_reason']] ?? 0) + 1; }
    if($r['source']) { $source_stats[$r['source']] = ($source_stats[$r['source']] ?? 0) + 1; }

    // ── AI Lead Scoring Logic ──
    $score = 0;
    if (!empty($r['budget'])) $score += 20;
    if ($r['pipeline_stage'] === 'negotiation') $score += 30;
    if ($r['pipeline_stage'] === 'proposal_sent') $score += 40;
    if (!empty($r['next_action_date']) && strtotime($r['next_action_date']) >= time()) $score += 50;
    
    // Last interaction pressure
    $days_since = $r['last_contacted'] ? floor((time() - strtotime($r['last_contacted'])) / 86400) : 999;
    if ($days_since >= 3 && !in_array($r['pipeline_stage'], ['won','lost'])) $score -= 20;
    if ($days_since >= 7 && !in_array($r['pipeline_stage'], ['won','lost'])) $score -= 30; // Severe penalty

    $r['score'] = $score;
    if ($score >= 80) $r['temp'] = ['label'=>'<i class="fas fa-fire"></i> HOT', 'color'=>'#ef4444', 'bg'=>'#fef2f2'];
    elseif ($score >= 50) $r['temp'] = ['label'=>'<i class="fas fa-circle text-warning"></i> WARM', 'color'=>'#d97706', 'bg'=>'#fef9c3'];
    else $r['temp'] = ['label'=>'<i class="fas fa-snowflake"></i>️ COLD', 'color'=>'#0284c7', 'bg'=>'#e0f2fe'];

    // Pressure Trackers
    $r['is_missing_action'] = empty($r['next_action']) && !in_array($r['pipeline_stage'], ['won','lost']);
    $r['is_due_today'] = !empty($r['next_action_date']) && date('Y-m-d', strtotime($r['next_action_date'])) === date('Y-m-d') && !in_array($r['pipeline_stage'], ['won','lost']);
    
    if ($r['is_missing_action']) $missing_action_count++;
    if ($r['is_due_today']) $due_today_count++;

    $leads[] = $r;
}

$pipeline = [];
foreach (array_keys($stage_meta) as $s) $pipeline[$s] = [];
foreach ($leads as $l) $pipeline[$l['pipeline_stage']][] = $l;

// ── Encode all leads for JS modals ───────────────────────────────────────────
$leads_js = [];
foreach ($leads as $l) $leads_js[(string)$l['id']] = $l;

$inp = "width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:13.5px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;";
?>

<!-- Include SortableJS for Drag and Drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<style>
/* ── UI/UX OVERHAUL STYLES (PREMIUM AGENCY EDITION) ── */

/* Base Typography & General */
.main-card { 
    background: #fff; 
    border-radius: 16px; 
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 10px 25px -3px rgba(0, 0, 0, 0.04); 
    overflow: hidden; 
    border: 1px solid #f0f3f2; 
}

/* Warning Alerts */
.pressure-alert { 
    background: #fffafa; 
    border: 1px solid #fee2e2; 
    border-left: 5px solid #ef4444;
    padding: 16px 24px; 
    border-radius: 12px; 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 24px; 
    box-shadow: 0 4px 12px rgba(220,38,38,0.06); 
}
.pressure-text { 
    font-size: 0.95rem; 
    font-weight: 700; 
    color: #dc2626; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
}

/* Top Header & Tabs */
.page-top-actions { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    padding: 20px 28px; 
    border-bottom: 1px solid #f4f5f4; 
    background: #fff; 
}
.nav-tabs-clean { 
    display: flex; 
    gap: 10px; 
    overflow-x: auto; 
    scrollbar-width: none; 
}
.nav-tabs-clean::-webkit-scrollbar { display: none; }
.nav-tab-btn {
    display: inline-flex; 
    align-items: center; 
    gap: 8px; 
    padding: 10px 20px; 
    border-radius: 50px; 
    font-size: 13.5px; 
    font-weight: 600;
    color: #64748b; 
    text-decoration: none; 
    background: #f8faf9; 
    border: 1px solid transparent; 
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
    white-space: nowrap;
}
.nav-tab-btn:hover { 
    background: #f0f7f4; 
    color: var(--primary); 
}
.nav-tab-btn.active { 
    background: var(--primary); 
    color: #fff; 
    box-shadow: 0 6px 14px rgba(2,68,66,0.18); 
}
.nav-tab-badge { 
    background: rgba(255,255,255,0.25); 
    padding: 3px 8px; 
    border-radius: 20px; 
    font-size: 11px; 
    font-weight: 800; 
    color: inherit; 
    letter-spacing: 0.3px;
}
.nav-tab-btn:not(.active) .nav-tab-badge { 
    background: #e2e8e4; 
    color: #475569; 
}

/* ── Pipeline kanban (Drag & Drop UI) ── */
.pipeline-board { 
    display: flex; 
    gap: 22px; 
    align-items: start; 
    padding: 28px 24px;
    background: #fbfdfc; 
    overflow-x: auto; 
    min-height: 75vh;
}
.pipeline-col { 
    background: #f4f7f5; 
    border-radius: 14px; 
    border: 1px solid #ebf0ec;
    display: flex; 
    flex-direction: column; 
    width: 310px; 
    flex-shrink: 0;
}
.pipeline-col-head {
    display: flex; 
    justify-content: space-between; 
    align-items: center;
    padding: 16px 18px; 
    border-radius: 14px 14px 0 0; 
    font-size: 0.82rem;
    font-weight: 800; 
    text-transform: uppercase; 
    letter-spacing: 0.6px;
    border-bottom: 2px solid rgba(0,0,0,0.03);
}
.pipeline-col-body { 
    padding: 14px; 
    min-height: 300px; 
    max-height: 65vh; 
    overflow-y: auto; 
    transition: background 0.3s ease;
}
.pipeline-col-body.sortable-ghost { 
    background: #f0f7fe; 
    border: 2px dashed #38bdf8; 
    border-radius: 0 0 14px 14px;
}
.pipeline-col-body::-webkit-scrollbar { width: 4px; }
.pipeline-col-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

/* Lead Cards (Intelligent) */
.pipeline-lead-card {
    background: #fff; 
    border-radius: 12px; 
    padding: 18px; 
    margin-bottom: 14px;
    border: 1px solid #f1f5f9; 
    cursor: grab; 
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.25s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.25s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.02), 0 4px 10px rgba(0,0,0,0.02); 
    position: relative;
}
.pipeline-lead-card.missing-action { 
    border-left: 4px solid #ef4444; 
}
.pipeline-lead-card:active { 
    cursor: grabbing; 
    transform: scale(0.97); 
    box-shadow: 0 12px 28px rgba(2,68,66,0.12); 
}
.pipeline-lead-card:hover { 
    border-color: var(--primary); 
    box-shadow: 0 8px 22px rgba(2,68,66,0.08); 
    transform: translateY(-3px); 
}

.plc-score { 
    position: absolute; 
    top: 14px; 
    right: 14px; 
    font-size: 0.65rem; 
    font-weight: 800; 
    padding: 4px 10px; 
    border-radius: 20px; 
    letter-spacing: 0.5px; 
}
.plc-name { 
    font-size: 1.05rem; 
    font-weight: 800; 
    color: #0f172a; 
    margin-bottom: 4px; 
    padding-right: 65px; 
    letter-spacing: -0.3px;
}
.plc-co { 
    font-size: 0.8rem; 
    color: #64748b; 
    margin-bottom: 14px; 
    display: flex; 
    align-items: center; 
    gap: 6px; 
    font-weight: 500;
}

.plc-next-action { 
    background: #f8fafc; 
    border: 1px solid #f1f5f9; 
    padding: 10px 12px; 
    border-radius: 8px; 
    margin-bottom: 14px; 
    font-size: 0.78rem; 
}
.plc-next-action strong { 
    display: block; 
    color: var(--primary); 
    margin-bottom: 4px; 
    font-weight: 700;
}
.plc-next-action.danger { 
    background: #fffafa; 
    border-color: #fecaca; 
    color: #dc2626; 
    font-weight: 800; 
}

.plc-wait { 
    font-size: 0.72rem; 
    font-weight: 800; 
    text-transform: uppercase; 
    margin-bottom: 10px; 
    display: flex; 
    gap: 6px; 
    align-items: center;
    letter-spacing: 0.4px;
}
.wait-client { color: #d97706; }
.wait-you { color: #dc2626; }

.plc-quick-actions { 
    display: flex; 
    gap: 8px; 
    border-top: 1px dashed #e2e8f0; 
    padding-top: 14px; 
    margin-top: 6px; 
}
.btn-quick { 
    flex: 1; 
    padding: 8px 6px; 
    border-radius: 8px; 
    border: 1px solid #e2e8f0; 
    background: #fff; 
    font-size: 0.72rem; 
    font-weight: 700; 
    color: #475569; 
    cursor: pointer; 
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
    text-align: center; 
    text-decoration: none; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    gap: 4px; 
}
.btn-quick:hover { 
    background: #f0f7f4; 
    color: var(--primary); 
    border-color: #bce3d4; 
}
.btn-wa { 
    background: #25D366; 
    color: #fff; 
    border-color: #25D366; 
    flex: 0 0 34px; 
}
.btn-wa:hover { 
    background: #1ebc5a; 
    color: #fff; 
    border-color: #1ebc5a; 
    box-shadow: 0 4px 10px rgba(37,211,102,0.2);
}

/* Analytics Tab */
.analytics-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); 
    gap: 24px; 
    padding: 24px; 
}
.analytics-card { 
    background: #fff; 
    border: 1px solid #f1f5f9; 
    border-radius: 16px; 
    padding: 28px; 
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 10px 20px -3px rgba(0, 0, 0, 0.03); 
    transition: transform 0.3s ease;
}
.analytics-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px -2px rgba(0, 0, 0, 0.03), 0 14px 24px -4px rgba(0, 0, 0, 0.05);
}
.analytics-card h3 { 
    font-size: 1.15rem; 
    color: #0f172a; 
    font-weight: 800; 
    margin-bottom: 24px; 
    letter-spacing: -0.2px;
}
.stat-list { 
    display: flex; 
    flex-direction: column; 
    gap: 14px; 
}
.stat-item { 
    display: flex; 
    justify-content: space-between; 
    font-size: 0.95rem; 
    padding: 10px 0; 
    border-bottom: 1px solid #f8fafc; 
    color: #475569;
}
.stat-item span:last-child { 
    font-weight: 800; 
    color: var(--primary); 
}
</style>
<div class="height-100">

<?php if ($missing_action_count > 0 || $due_today_count > 0): ?>
<div class="pressure-alert">
    <div class="pressure-text">
        <i class="fas fa-exclamation-triangle" style="font-size:1.2rem;"></i> 
        Action Required: You have <?php echo $missing_action_count; ?> lead(s) with NO Next Action and <?php echo $due_today_count; ?> task(s) due today.
    </div>
</div>
<?php endif; ?>

<div class="main-card">

    <div class="page-top-actions">
        <div class="nav-tabs-clean">
            <?php
            $tabs=[
                'pipeline'  =>['<i class="fas fa-magnet"></i>','Pipeline Kanban'],
                'list'      =>['<i class="fas fa-clipboard"></i>','All Leads List'],
                'analytics' =>['<i class="fas fa-chart-bar"></i>','Conversion Analytics'],
            ];
            foreach($tabs as $tk=>$td): $a=($tab===$tk); ?>
            <a href="manage_leads.php?tab=<?php echo $tk; ?>" class="nav-tab-btn <?php echo $a ? 'active' : ''; ?>">
                <?php echo $td[0].' '.$td[1]; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div>
            <button onclick="openLeadModal('add')" class="btn-primary-custom" style="padding: 10px 20px; border-radius: 50px;">+ Add New Lead</button>
        </div>
    </div>

    <?php if ($tab === 'pipeline'): ?>
    <!-- ════ PIPELINE (DRAG & DROP KANBAN) ════ -->
    <div class="pipeline-board">
        <?php foreach ($stage_meta as $sk => $sm): ?>
        <div class="pipeline-col">
            <div class="pipeline-col-head" style="background:<?php echo $sm['color']; ?>;color:#fff;">
                <?php echo $sm['emoji'].' '.$sm['label']; ?>
                <span class="pipeline-col-count" id="count-<?php echo $sk; ?>" style="color: <?php echo $sm['color']; ?>; background: #fff;"><?php echo count($pipeline[$sk]); ?></span>
            </div>
            
            <div class="pipeline-col-body sortable-list" data-stage="<?php echo $sk; ?>" id="stage-<?php echo $sk; ?>">
                <?php foreach ($pipeline[$sk] as $l): ?>
                <div class="pipeline-lead-card <?php echo $l['is_missing_action'] ? 'missing-action' : ''; ?>" data-id="<?php echo $l['id']; ?>">
                    
                    <div class="plc-score" style="background: <?php echo $l['temp']['bg']; ?>; color: <?php echo $l['temp']['color']; ?>;">
                        <?php echo $l['temp']['label']; ?> (<?php echo $l['score']; ?>)
                    </div>

                    <div class="plc-name" onclick="openLeadModal('edit', '<?php echo $l['id']; ?>')"><?php echo htmlspecialchars($l['name']); ?></div>
                    <div class="plc-co"><i class="fas fa-building"></i> <?php echo htmlspecialchars($l['company'] ?: 'No Company'); ?></div>
                    
                    <?php if ($l['waiting_on'] === 'client'): ?>
                        <div class="plc-wait wait-client">⏳ Waiting on Client</div>
                    <?php elseif ($l['waiting_on'] === 'you'): ?>
                        <div class="plc-wait wait-you"><i class="fas fa-fire"></i> Waiting on YOU</div>
                    <?php endif; ?>

                    <?php if ($l['is_missing_action']): ?>
                        <div class="plc-next-action danger"><i class="fas fa-exclamation-triangle"></i>️ NO NEXT ACTION SET</div>
                    <?php else: ?>
                        <div class="plc-next-action">
                            <strong>Next: <?php echo htmlspecialchars($l['next_action']); ?></strong>
                            <i class="fas fa-calendar-alt"></i> <?php echo $l['next_action_date'] ? date('M j', strtotime($l['next_action_date'])) : 'Anytime'; ?>
                        </div>
                    <?php endif; ?>

                    <div style="font-size:0.7rem; color:<?php echo ($l['last_contacted'] && strtotime($l['last_contacted']) < time() - 3*86400) ? '#dc2626; font-weight:700;' : '#888;'; ?> margin-bottom: 8px;">
                        Last Contact: <?php echo timeAgo($l['last_contacted']); ?>
                    </div>

                    <div class="plc-quick-actions">
                        <?php if ($l['phone']): 
                            $wa = cleanPhoneWa($l['phone']);
                        ?>
                        <a href="https://wa.me/<?php echo $wa; ?>" target="_blank" class="btn-quick btn-wa" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                        <?php endif; ?>

                        <form method="POST" style="flex:1; display:flex;">
                            <input type="hidden" name="quick_action" value="followup_tomorrow">
                            <input type="hidden" name="lead_id" value="<?php echo $l['id']; ?>">
                            <button type="submit" class="btn-quick" title="Bump followup to tomorrow">+1 Day</button>
                        </form>
                        
                        <?php if ($sk === 'new'): ?>
                        <form method="POST" style="flex:1; display:flex;">
                            <input type="hidden" name="quick_action" value="mark_contacted">
                            <input type="hidden" name="lead_id" value="<?php echo $l['id']; ?>">
                            <button type="submit" class="btn-quick">Contacted</button>
                        </form>
                        <?php elseif ($sk === 'qualified'): ?>
                        <form method="POST" style="flex:1; display:flex;">
                            <input type="hidden" name="quick_action" value="send_proposal">
                            <input type="hidden" name="lead_id" value="<?php echo $l['id']; ?>">
                            <button type="submit" class="btn-quick">Sent Prop.</button>
                        </form>
                        <?php elseif ($sk === 'negotiation'): ?>
                        <form method="POST" style="flex:1; display:flex;">
                            <input type="hidden" name="quick_action" value="mark_won">
                            <input type="hidden" name="lead_id" value="<?php echo $l['id']; ?>">
                            <button type="submit" class="btn-quick" style="color:#10b981; border-color:#10b981;">WIN!</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($pipeline[$sk])): ?>
                <div class="empty-state-msg" style="text-align:center;padding:30px 8px;color:#cbd5e1;font-size:0.85rem;font-weight:600;border:2px dashed #e5e7eb;border-radius:10px;margin-top:10px;pointer-events:none;">Drop leads here</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Drag & Drop Logic -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const columns = document.querySelectorAll('.sortable-list');
        columns.forEach(col => {
            new Sortable(col, {
                group: 'pipeline',
                animation: 150,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onEnd: function(evt) {
                    const itemEl = evt.item;
                    const leadId = itemEl.getAttribute('data-id');
                    const toStage = evt.to.getAttribute('data-stage');
                    const fromStage = evt.from.getAttribute('data-stage');
                    
                    const emptyMsg = evt.to.querySelector('.empty-state-msg');
                    if(emptyMsg) emptyMsg.style.display = 'none';

                    if(fromStage !== toStage) {
                        const fromCountEl = document.getElementById('count-' + fromStage);
                        const toCountEl = document.getElementById('count-' + toStage);
                        fromCountEl.textContent = parseInt(fromCountEl.textContent) - 1;
                        toCountEl.textContent = parseInt(toCountEl.textContent) + 1;

                        const formData = new FormData();
                        formData.append('ajax_action', 'update_stage');
                        formData.append('lead_id', leadId);
                        formData.append('new_stage', toStage);

                        fetch('manage_leads.php', { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            if(data.success) { showToast('Stage & Logic updated!', 'success'); setTimeout(()=>window.location.reload(), 1000); } 
                            else { showToast('Failed to update database.', 'error'); }
                        });
                    }
                }
            });
        });
    });
    </script>

    <?php elseif ($tab === 'list'): ?>
    <!-- ════ LIST VIEW ════ -->
    <div style="padding:24px;">
        <form method="GET" style="display:flex;gap:12px;margin-bottom:20px;">
            <input type="hidden" name="tab" value="list">
            <div class="search-box" style="max-width:400px; flex:1;">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, email, company, phone…" style="padding: 12px 16px 12px 40px; border-radius: 12px; border: 1px solid #ddd; width: 100%;">
                <i class="fas fa-search search-icon" style="position: absolute; left: 14px; top: 14px; color: #aaa;"></i>
            </div>
            <button type="submit" class="btn-primary-custom" style="padding: 10px 24px; border-radius: 12px;">Search</button>
            <?php if ($search): ?><a href="manage_leads.php?tab=list" class="btn-secondary-custom" style="padding: 10px 20px; border-radius: 12px; text-decoration: none;">Clear</a><?php endif; ?>
        </form>

        <div class="main-table" style="border: 1px solid #f0f0f0; border-radius: 16px;">
            <div class="table-head" style="grid-template-columns:2.5fr 1fr 1fr 1fr 1fr;">
                <span>Lead Info</span><span>Next Action</span><span>Stage</span><span>Value</span><span>Actions</span>
            </div>
            <?php if (!empty($leads)): foreach ($leads as $l): $sm = $stage_meta[$l['pipeline_stage']] ?? $stage_meta['new']; ?>
            <div class="table-row" style="grid-template-columns:2.5fr 1fr 1fr 1fr 1fr; align-items: center;">
                <div class="client">
                    <div class="avatar <?php echo leadAvatar($l['name']); ?>"><?php echo strtoupper($l['name'][0]); ?></div>
                    <div>
                        <strong style="color: var(--primary); font-size: 0.95rem; cursor:pointer;" onclick="openLeadModal('edit','<?php echo $l['id']; ?>')"><?php echo htmlspecialchars($l['name']); ?></strong>
                        <small style="color: #888; font-size: 0.8rem;"><span style="color:<?php echo $l['temp']['color']; ?>; font-weight:800;"><?php echo $l['temp']['label']; ?></span> | <?php echo htmlspecialchars($l['company']?:'—'); ?></small>
                    </div>
                </div>
                <div style="font-size: 0.8rem; color: <?php echo $l['is_missing_action']?'#dc2626':'#555'; ?>; font-weight: 600;">
                    <?php echo $l['is_missing_action'] ? '<i class="fas fa-exclamation-triangle"></i>️ No Action Set' : htmlspecialchars($l['next_action']); ?>
                </div>
                <span>
                    <span style="background:<?php echo $sm['bg']; ?>;color:<?php echo $sm['color']; ?>;padding:4px 12px;border-radius:20px;font-size:0.75rem;font-weight:700;white-space:nowrap;">
                        <?php echo $sm['emoji'].' '.$sm['label']; ?>
                    </span>
                </span>
                <span style="font-size:0.9rem;font-weight:700;color:var(--primary);">
                    <?php echo $l['deal_value']>0 ? 'Rs.'.number_format($l['deal_value']) : '—'; ?>
                </span>
                <div style="display:flex;gap:6px;align-items:center;">
                    <button class="dots" title="Edit" onclick="openLeadModal('edit','<?php echo $l['id']; ?>')"><i class="fas fa-pencil-alt"></i>️</button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this lead?');">
                        <input type="hidden" name="delete_lead" value="1">
                        <input type="hidden" name="lead_id"     value="<?php echo $l['id']; ?>">
                        <button type="submit" class="dots" style="color:#ef4444;" title="Delete"><i class="fas fa-trash"></i>️</button>
                    </form>
                </div>
            </div>
            <?php endforeach; else: ?>
            <div style="text-align:center;padding:60px 24px;color:#888;">No leads found.</div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($tab === 'analytics'): ?>
    <!-- ════ CONVERSION ANALYTICS ════ -->
    <div class="analytics-grid">
        <div class="analytics-card">
            <h3>Sales Performance</h3>
            <div class="stat-list">
                <div class="stat-item"><span>Total Leads</span> <span><?php echo $total_leads_val; ?></span></div>
                <div class="stat-item"><span>Deals Won</span> <span><?php echo $won_count; ?></span></div>
                <div class="stat-item"><span>Conversion Rate</span> <span><?php echo $total_leads_val > 0 ? round(($won_count / $total_leads_val)*100, 1) : 0; ?>%</span></div>
                <div class="stat-item"><span>Avg Deal Value</span> <span>Rs.<?php echo $won_count > 0 ? number_format($total_won_val / $won_count) : 0; ?></span></div>
                <div class="stat-item"><span>Total Pipeline Value (Won)</span> <span>Rs.<?php echo number_format($total_won_val); ?></span></div>
            </div>
        </div>

        <div class="analytics-card">
            <h3>Lead Sources</h3>
            <div class="stat-list">
                <?php arsort($source_stats); foreach($source_stats as $src => $count): ?>
                <div class="stat-item"><span><?php echo $source_labels[$src] ?? 'Other'; ?></span> <span><?php echo $count; ?></span></div>
                <?php endforeach; if(empty($source_stats)) echo "<div style='color:#888;font-size:0.9rem;'>No source data yet.</div>"; ?>
            </div>
        </div>

        <div class="analytics-card" style="grid-column: 1 / -1;">
            <h3>Lost Reasons (Why deals are dying)</h3>
            <div class="stat-list">
                <?php arsort($lost_reasons); foreach($lost_reasons as $reason => $count): ?>
                <div class="stat-item"><span><?php echo htmlspecialchars($reason); ?></span> <span style="color:#dc2626;"><?php echo $count; ?></span></div>
                <?php endforeach; if(empty($lost_reasons)) echo "<div style='color:#888;font-size:0.9rem;'>No lost reason data yet.</div>"; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.main-card -->
</div><!-- /.height-100 -->

<!-- ════ ADD/EDIT LEAD MODAL (Intelligent) ════ -->
<div id="leadModal" class="modal-overlay" onclick="if(event.target===this)closeLeadModal()">
    <div class="modal-content" style="max-width:800px; width: 95%;">
        <div class="modal-header">
            <h4><div class="modal-header-icon"><i class="fas fa-user"></i></div><span id="leadModalTitle">Add Lead</span></h4>
            <button class="modal-close" onclick="closeLeadModal()">×</button>
        </div>
        <form method="POST" id="leadForm">
            <input type="hidden" name="add_lead"  id="lActionAdd"  value="1">
            <input type="hidden" name="edit_lead" id="lActionEdit" value="">
            <input type="hidden" name="lead_id"   id="lId"         value="">
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    
                    <!-- Left Col: Lead Info -->
                    <div>
                        <h5 style="margin-top:0; color:var(--primary); border-bottom:1px solid #eee; padding-bottom:8px;">Lead Details</h5>
                        <label class="form-label mt-2">Full Name <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="l_name" id="lName" class="form-control mb-2" placeholder="John Doe" required>
                        
                        <label class="form-label">Company</label>
                        <input type="text" name="l_company" id="lCompany" class="form-control mb-2" placeholder="Acme Corp">
                        
                        <label class="form-label">Phone & Email</label>
                        <div style="display:flex; gap:10px; margin-bottom: 8px;">
                            <input type="tel" name="l_phone" id="lPhone" class="form-control" placeholder="+92 300 1234567">
                            <input type="email" name="l_email" id="lEmail" class="form-control" placeholder="email@example.com">
                        </div>

                        <label class="form-label">Source & Budget</label>
                        <div style="display:flex; gap:10px; margin-bottom: 8px;">
                            <select name="l_source" id="lSource" class="form-control">
                                <?php foreach ($source_labels as $v=>$lbl): ?><option value="<?php echo $v; ?>"><?php echo $lbl; ?></option><?php endforeach; ?>
                            </select>
                            <input type="text" name="l_budget" id="lBudget" class="form-control" placeholder="e.g. 50k-100k">
                        </div>

                        <label class="form-label">Service Interest</label>
                        <input type="text" name="l_service" id="lService" class="form-control" placeholder="e.g. Branding, SEO">
                    </div>

                    <!-- Right Col: CRM Action Logic -->
                    <div style="background: #f9fbfc; padding: 16px; border-radius: 12px; border: 1px solid #e5e7eb;">
                        <h5 style="margin-top:0; color:var(--primary); border-bottom:1px solid #eee; padding-bottom:8px;">Pipeline Action Engine</h5>
                        
                        <label class="form-label mt-2">Pipeline Stage</label>
                        <select name="l_stage" id="lStage" class="form-control mb-2" style="font-weight:700;">
                            <?php foreach ($stage_meta as $sk=>$sm): ?><option value="<?php echo $sk; ?>"><?php echo $sm['emoji'].' '.$sm['label']; ?></option><?php endforeach; ?>
                        </select>

                        <div id="lostReasonRow" style="display:none; background: #fff5f5; padding: 10px; border-radius: 8px; border: 1px solid #fecaca; margin-bottom: 8px;">
                            <label class="form-label" style="color:#dc2626;">Reason for Loss</label>
                            <input type="text" name="l_lost_reason" id="lLostReason" class="form-control" style="background:#fff;border-color:#fca5a5;" placeholder="Why didn't this close?">
                        </div>

                        <div style="background: #fff; padding: 12px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 8px;">
                            <label class="form-label" style="color:#d97706;"> Next Action</label>
                            <input type="text" name="l_next_action" id="lNextAction" class="form-control mb-2" placeholder="e.g. Send formal proposal">
                            <input type="datetime-local" name="l_next_action_date" id="lNextActionDate" class="form-control">
                        </div>

                        <label class="form-label">Waiting On</label>
                        <select name="l_waiting_on" id="lWaitingOn" class="form-control mb-2">
                            <option value="none">None (Active)</option>
                            <option value="client">⏳ Client (Waiting for reply/payment)</option>
                            <option value="you">YOU (You owe them something)</option>
                        </select>

                        <label class="form-label">Deal Value (Rs.)</label>
                        <input type="number" name="l_deal_value" id="lDealValue" class="form-control" placeholder="0" min="0">
                    </div>
                </div>
                
                <div style="margin-top: 16px;">
                    <label class="form-label">Internal Notes</label>
                    <textarea name="l_notes" id="lNotes" class="form-control" style="resize:vertical;" rows="2" placeholder="Initial requirements, background info..."></textarea>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeLeadModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="leadSubmitBtn"><i class="fas fa-user"></i> Add Lead</button>
            </div>
        </form>
    </div>
</div>

<div id="toastContainer" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;"></div>

<script>
const leadsData = <?php echo json_encode($leads_js, JSON_HEX_TAG | JSON_HEX_APOS); ?>;

// ── Lead modal ────────────────────────────────────────────────────────────────
function openLeadModal(mode, id) {
    const isEdit = mode === 'edit';
    const sid    = String(id ?? '');
    document.getElementById('leadModalTitle').textContent = isEdit ? 'Edit Lead' : 'Add New Lead';
    document.getElementById('leadSubmitBtn').textContent  = isEdit ? '<i class="fas fa-pencil-alt"></i>️ Update Lead' : '<i class="fas fa-user"></i> Add Lead';
    document.getElementById('lActionAdd').value           = isEdit ? '' : '1';
    document.getElementById('lActionEdit').value          = isEdit ? '1' : '';
    document.getElementById('lId').value                  = sid;

    if (isEdit && leadsData[sid]) {
        const l = leadsData[sid];
        document.getElementById('lName').value      = l.name            || '';
        document.getElementById('lCompany').value   = l.company         || '';
        document.getElementById('lEmail').value     = l.email           || '';
        document.getElementById('lPhone').value     = l.phone           || '';
        document.getElementById('lSource').value    = l.source          || 'other';
        document.getElementById('lService').value   = l.service_interest|| '';
        document.getElementById('lStage').value     = l.pipeline_stage  || 'new';
        document.getElementById('lBudget').value    = l.budget          || '';
        document.getElementById('lDealValue').value = l.deal_value > 0 ? l.deal_value : '';
        document.getElementById('lNotes').value     = l.notes           || '';
        document.getElementById('lLostReason').value= l.lost_reason     || '';
        
        document.getElementById('lNextAction').value= l.next_action     || '';
        document.getElementById('lNextActionDate').value = l.next_action_date ? l.next_action_date.slice(0,16) : '';
        document.getElementById('lWaitingOn').value = l.waiting_on      || 'none';

        toggleLostReason(l.pipeline_stage);
    } else {
        document.getElementById('leadForm').reset();
        document.getElementById('lActionAdd').value  = '1';
        document.getElementById('lActionEdit').value = '';
        toggleLostReason('new');
    }
    document.getElementById('leadModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeLeadModal() { document.getElementById('leadModal').classList.remove('active'); document.body.style.overflow=''; }

document.getElementById('lStage').addEventListener('change', function(){ toggleLostReason(this.value); });
function toggleLostReason(stage) {
    document.getElementById('lostReasonRow').style.display = stage === 'lost' ? 'block' : 'none';
}

function showToast(msg, type='success') {
    const color = type==='success' ? '#10b981' : '#ef4444';
    const t = document.createElement('div');
    t.style.cssText = `background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 8px 24px rgba(0,0,0,.08);font-size:13.5px;min-width:220px;border-left:4px solid ${color};z-index:9999;`;
    t.innerHTML = `<span style="font-weight:700;color:${color};font-size:1.1rem;">${type==='success'?'<i class="fas fa-check"></i>':'<i class="fas fa-times"></i>'}</span><span style="font-weight:500;color:#333;">${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(()=>{ t.style.opacity='0'; t.style.transition='opacity .3s ease-out'; setTimeout(()=>t.remove(),300); }, 3500);
}
document.addEventListener('DOMContentLoaded', () => {
    <?php if(isset($_GET['saved'])):   ?> showToast('Lead saved successfully!');    <?php endif; ?>
    <?php if(isset($_GET['deleted'])): ?> showToast('Lead deleted.', 'error');  <?php endif; ?>
    <?php if(isset($_GET['action_success'])): ?> showToast('Action logged!');  <?php endif; ?>
});
document.addEventListener('keydown', e => { if (e.key==='Escape') { closeLeadModal(); } });
</script>

<?php include('dashboard_footer.php'); ?>