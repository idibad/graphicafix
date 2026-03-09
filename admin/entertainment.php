<?php
include('dashboard_header.php');

// ── Handle AJAX score save ────────────────────────────────────────────────────
if (isset($_POST['ajax_save_score'])) {
    header('Content-Type: application/json');
    $game     = $_POST['game']  ?? '';
    $score    = intval($_POST['score'] ?? 0);
    $allowed  = ['brick_breaker','asteroid_dodge'];

    if (!in_array($game, $allowed) || $score <= 0) {
        echo json_encode(['success'=>false]); exit;
    }

    $userId   = $_SESSION['user_id']  ?? null;
    $username = $_SESSION['username'] ?? $_SESSION['name'] ?? 'Anonymous';

    $stmt = $conn->prepare("INSERT INTO game_scores (user_id, username, game, score) VALUES (?,?,?,?)");
    $stmt->bind_param("issi", $userId, $username, $game, $score);
    $stmt->execute();

    // Global rank
    $esc  = intval($score);
    $rank = (int)$conn->query("SELECT COUNT(*)+1 AS r FROM game_scores WHERE game='$game' AND score > $esc")->fetch_assoc()['r'];

    // Personal best
    $uid_clause = $userId ? "AND user_id=".intval($userId) : "AND username='".addslashes($username)."'";
    $best = (int)$conn->query("SELECT MAX(score) AS s FROM game_scores WHERE game='$game' $uid_clause")->fetch_assoc()['s'];

    echo json_encode(['success'=>true,'rank'=>$rank,'best'=>$best]);
    exit;
}

// ── Leaderboard data ──────────────────────────────────────────────────────────
$topBrick = $conn->query("SELECT username, score, played_at FROM game_scores WHERE game='brick_breaker'  ORDER BY score DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$topDodge = $conn->query("SELECT username, score, played_at FROM game_scores WHERE game='asteroid_dodge' ORDER BY score DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$brickPlays = (int)$conn->query("SELECT COUNT(*) c FROM game_scores WHERE game='brick_breaker'")->fetch_assoc()['c'];
$dodgePlays = (int)$conn->query("SELECT COUNT(*) c FROM game_scores WHERE game='asteroid_dodge'")->fetch_assoc()['c'];

$uid     = $_SESSION['user_id'] ?? 0;
$myBrick = $myDodge = 0;
if ($uid) {
    $myBrick = (int)$conn->query("SELECT MAX(score) s FROM game_scores WHERE game='brick_breaker'  AND user_id=$uid")->fetch_assoc()['s'];
    $myDodge = (int)$conn->query("SELECT MAX(score) s FROM game_scores WHERE game='asteroid_dodge' AND user_id=$uid")->fetch_assoc()['s'];
}

$currentUser = htmlspecialchars($_SESSION['username'] ?? $_SESSION['name'] ?? 'Player', ENT_QUOTES);
?>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{
    --neon-pink:#ff2d9b; --neon-yellow:#ffe600;
    --neon-blue: var(--accent); --neon-green: var(--accent);
    --light-bg:#f9f9f9;   --card-bg:#ffffff;
    --card-border:#d0d4da; --text-dim:#6c7a8a;
    --fd:'Orbitron',monospace; --fb:'Rajdhani',sans-serif;
}

/* ── Wrapper ───────────────────────────────────────────── */
.ent-wrap{
    background:; border-radius:16px;
    padding:28px 28px 48px; margin-bottom:30px;
    position:relative; overflow:hidden;
}
.ent-wrap::before{
    content:''; position:absolute; inset:0; pointer-events:none;
    background-image:
        linear-gradient(rgba(0,200,255,.05) 1px,transparent 1px),
        linear-gradient(90deg,rgba(0,200,255,.05) 1px,transparent 1px);
    background-size:40px 40px;
}

/* ── Hero ──────────────────────────────────────────────── */
.ent-hero{text-align:left;padding:20px 0 36px;position:relative;}
.ent-tag {font-family:var(--fd);font-size:9px;letter-spacing:.35em;color:var(--neon-green);text-transform:uppercase;margin-bottom:10px;}
.ent-h1  {font-family:var(--fd);font-size:clamp(26px,4.5vw,50px);font-weight:900;color:#0d0d0d;line-height:1;margin:0;text-shadow:0 0 10px rgba(0,200,255,.2);}
.ent-h1 span{color:var(--neon-blue);text-shadow:0 0 12px var(--neon-blue);}
.ent-sub {font-family:var(--fb);font-size:15px;color:var(--text-dim);margin-top:10px;letter-spacing:.05em;}

.pb-strip{display:flex;justify-content:left;gap:24px;margin-top:18px;flex-wrap:wrap;}
.pb-chip{
    font-family:var(--fd);font-size:10px;letter-spacing:.1em;
    padding:6px 18px;border-radius:100px;
    border:1px solid var(--card-border);background:var(--card-bg);color:var(--text-dim);
}
.pb-chip span{color:#0d0d0d;margin-left:6px;}

/* ── Games grid ────────────────────────────────────────── */
.games-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:24px;}
@media(max-width:900px){.games-grid{grid-template-columns:1fr;}}
@media(max-width:500px){.games-grid{gap:16px;}}

/* ── Game card ─────────────────────────────────────────── */
.game-card{
    background:var(--card-bg);border:1px solid var(--card-border);
    border-radius:14px;overflow:hidden;position:relative;
    transition:transform .2s,box-shadow .2s;
}
.game-card:hover{transform:translateY(-3px);}

.gc-header{
    padding:14px 20px 12px;display:flex;align-items:center;
    justify-content:space-between;border-bottom:1px solid var(--card-border);
}
.gc-title-row{display:flex;align-items:center;gap:10px;}
.gc-icon{font-size:20px;color:var(--neon-blue);}
.gc-name{font-family:var(--fd);font-size:12px;font-weight:700;color:#0d0d0d;letter-spacing:.08em;}

.score-badge{
    font-family:var(--fd);font-size:10px;font-weight:700;
    padding:4px 12px;border-radius:100px;
}

.gc-canvas-wrap{
    position:relative;display:flex;align-items:center;justify-content:center;
    background:#e5e7eb;
}
canvas{display:block;image-rendering:pixelated;}

.gc-controls{
    padding:10px 16px;display:flex;align-items:center;
    justify-content:space-between;gap:8px;
    border-top:1px solid var(--card-border);flex-wrap:wrap;
}
.ctrl-hint{font-family:var(--fb);font-size:12px;color:var(--text-dim);letter-spacing:.04em;}
.ctrl-hint kbd{
    background:#f0f0f5;border:1px solid #c0c4d0;border-radius:4px;
    padding:1px 6px;font-size:11px;color:#0d0d0d;font-family:var(--fb);
}
.btn-reset{
    font-family:var(--fd);font-size:9px;font-weight:700;letter-spacing:.1em;
    padding:6px 14px;border-radius:6px;border:1px solid var(--card-border);
    background:transparent;color:var(--text-dim);cursor:pointer;
    transition:all .15s;text-transform:uppercase;
}

/* ── Overlay ───────────────────────────────────────────── */
.game-overlay{
    position:absolute;inset:0;
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    background:rgba(245,245,245,.95);backdrop-filter:blur(3px);
    z-index:10;gap:8px;padding:20px;
}
.ov-title{font-family:var(--fd);font-size:17px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:var(--neon-blue);text-shadow:0 0 10px var(--neon-blue);}
.ov-msg  {font-family:var(--fd);font-size:11px;color:#6c7a8a;letter-spacing:.1em;text-align:center;}
.ov-rank {font-family:var(--fd);font-size:10px;letter-spacing:.1em;text-align:center;margin-top:2px;}
.ov-btn  {
    margin-top:10px;font-family:var(--fd);font-size:10px;font-weight:700;
    letter-spacing:.15em;padding:10px 26px;border-radius:8px;border:2px solid var(--neon-blue);
    cursor:pointer;text-transform:uppercase;transition:all .15s;
    background:color-mix(in srgb,var(--neon-blue) 10%,transparent);color:var(--neon-blue);
}
.ov-btn:hover{opacity:.85;transform:scale(1.04);}
.ov-saving{
    font-family:var(--fb);font-size:12px;color:var(--text-dim);
    animation:pulse .8s ease-in-out infinite alternate;
}

/* ── Colour themes ─────────────────────────────────────── */
.game-brick{--gc:var(--neon-pink);}
.game-dodge{--gc:var(--neon-yellow);}

.game-card:hover   {box-shadow:0 8px 40px rgba(0,0,0,.1),0 0 0 1px var(--gc,#d0d4da);}
.score-badge       {background:color-mix(in srgb,var(--gc) 14%,transparent);color:var(--gc);}
.ov-btn            {border-color:var(--gc);background:color-mix(in srgb,var(--gc) 10%,transparent);color:var(--gc);}
.btn-reset:hover   {border-color:var(--gc);color:var(--gc);}

/* ── Leaderboard ───────────────────────────────────────── */
.lb-section{margin-top:44px;}

.lb-head{display:flex;align-items:center;gap:14px;margin-bottom:22px;}
.lb-title{
    font-family:var(--fd);font-size:17px;font-weight:900;
    color:#0d0d0d;letter-spacing:.12em;white-space:nowrap;
}
.lb-title span{color:var(--neon-blue);text-shadow:0 0 10px var(--neon-blue);}
.lb-divider{flex:1;height:1px;background:linear-gradient(90deg,var(--card-border),transparent);}

.lb-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px;}
@media(max-width:900px){.lb-grid{grid-template-columns:1fr;}}
@media(max-width:500px){.lb-grid{gap:16px;}}

.lb-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:12px;overflow:hidden;}

.lb-card-head{
    padding:13px 18px;display:flex;align-items:center;
    justify-content:space-between;border-bottom:1px solid var(--card-border);
}
.lb-card-title{font-family:var(--fd);font-size:11px;font-weight:700;color:#0d0d0d;letter-spacing:.1em;display:flex;align-items:center;gap:8px;}
.lb-plays{font-family:var(--fb);font-size:12px;color:var(--text-dim);}

.lb-tbl{width:100%;border-collapse:collapse;}
.lb-tbl th{
    padding:9px 16px;text-align:left;
    font-family:var(--fd);font-size:8px;font-weight:700;
    color:var(--text-dim);text-transform:uppercase;letter-spacing:.14em;
    border-bottom:1px solid var(--card-border);background:rgba(0,0,0,.02);
}
.lb-tbl th.r{text-align:right;}
.lb-tbl td{
    padding:10px 16px;font-family:var(--fb);font-size:14px;
    color:#0d0d0d;border-bottom:1px solid rgba(208,212,218,.5);
    vertical-align:middle;
}
.lb-tbl tr:last-child td{border-bottom:none;}
.lb-tbl td.r{text-align:right;}

.rank-1 td{color:#0d0d0d;background:rgba(255,230,0,.08);}
.rank-2 td{color:#6c7a8a;}
.rank-3 td{color:#a0a0a0;}

.medal{font-size:16px;line-height:1;}
.p-name{font-weight:600;color:#1a202c;}
.rank-1 .p-name{color:#0d0d0d;}

.s-num{font-family:var(--fd);font-size:13px;font-weight:700;}
.rank-1 .s-num{color:var(--neon-yellow);text-shadow:0 0 6px rgba(255,230,0,.5);}
.rank-2 .s-num{color:#8a8a8a;}
.rank-3 .s-num{color:#cd7f32;}

.s-date{font-size:12px;color:var(--text-dim);}
.lb-empty{
    padding:34px 16px;text-align:center;
    font-family:var(--fb);font-size:14px;color:var(--text-dim);letter-spacing:.06em;
}

@keyframes pulse{to{opacity:.3;}}
@keyframes rowIn{from{opacity:0;background:rgba(0,255,157,.05);}to{opacity:1;}}
</style>

<div class="ent-wrap">

<!-- ── Hero ─────────────────────────────────────────────────── -->
<div class="ent-hero">
    <div class="ent-tag">⚡ Break Room</div>
    <h1 class="ent-h1">GAME <span>ZONE</span></h1>
    <div class="pb-strip">
        <div class="pb-chip">🔴 MY BRICK BEST <span id="myBrickBest"><?= $myBrick ?: '—' ?></span></div>
        <div class="pb-chip">🚀 MY DODGE BEST <span id="myDodgeBest"><?= $myDodge ?: '—' ?></span></div>
    </div>
</div>

<!-- ── Games ─────────────────────────────────────────────────── -->
<div class="games-grid">

    <!-- BRICK BREAKER -->
    <div class="game-card game-brick">
        <div class="gc-header">
            <div class="gc-title-row">
                <span class="gc-icon">🔴</span>
                <span class="gc-name">BRICK BREAKER</span>
            </div>
            <span class="score-badge" id="brickBadge">SCORE: 0</span>
        </div>
        <div class="gc-canvas-wrap" style="height:320px;">
            <canvas id="brickCanvas" width="480" height="320"></canvas>
            <div class="game-overlay" id="brickOverlay">
                <div class="ov-title">BRICK BREAKER</div>
                <div class="ov-msg"    id="brickMsg">Break all the bricks!</div>
                <div class="ov-rank"   id="brickRank" style="display:none;"></div>
                <div class="ov-saving" id="brickSaving" style="display:none;">Saving score…</div>
                <button class="ov-btn" id="brickBtn" onclick="startBrick()">▶ START</button>
            </div>
        </div>
        <div class="gc-controls">
            <div class="ctrl-hint"><kbd>←</kbd><kbd>→</kbd> or <kbd>Mouse</kbd> to move paddle</div>
            <button class="btn-reset" onclick="resetBrick()">↺ Reset</button>
        </div>
    </div>

    <!-- ASTEROID DODGE -->
    <div class="game-card game-dodge">
        <div class="gc-header">
            <div class="gc-title-row">
                <span class="gc-icon">🚀</span>
                <span class="gc-name">ASTEROID DODGE</span>
            </div>
            <span class="score-badge" id="dodgeBadge">SCORE: 0</span>
        </div>
        <div class="gc-canvas-wrap" style="height:320px;">
            <canvas id="dodgeCanvas" width="480" height="320"></canvas>
            <div class="game-overlay" id="dodgeOverlay">
                <div class="ov-title">ASTEROID DODGE</div>
                <div class="ov-msg"    id="dodgeMsg">Survive as long as you can!</div>
                <div class="ov-rank"   id="dodgeRank" style="display:none;"></div>
                <div class="ov-saving" id="dodgeSaving" style="display:none;">Saving score…</div>
                <button class="ov-btn" id="dodgeBtn" onclick="startDodge()">▶ START</button>
            </div>
        </div>
        <div class="gc-controls">
            <div class="ctrl-hint"><kbd>←</kbd><kbd>→</kbd> or <kbd>Mouse</kbd> to dodge asteroids</div>
            <button class="btn-reset" onclick="resetDodge()">↺ Reset</button>
        </div>
    </div>
</div>

<!-- ── Leaderboard ────────────────────────────────────────────── -->
<div class="lb-section">
    <div class="lb-head">
        <div class="lb-title">🏆 LEADER<span>BOARD</span></div>
        <div class="lb-divider"></div>
    </div>

    <div class="lb-grid">

        <!-- Brick leaderboard -->
        <div class="lb-card game-brick">
            <div class="lb-card-head">
                <div class="lb-card-title">🔴 BRICK BREAKER</div>
                <div class="lb-plays" id="brickPlaysCount"><?= $brickPlays ?> plays</div>
            </div>
            <?php if (empty($topBrick)): ?>
            <div class="lb-empty" id="brickLbEmpty">No scores yet — be the first! 🎮</div>
            <?php else: ?>
            <table class="lb-tbl">
                <thead><tr>
                    <th style="width:38px">#</th>
                    <th>Player</th>
                    <th class="r">Score</th>
                    <th class="r">Date</th>
                </tr></thead>
                <tbody id="brickLbBody">
                <?php foreach ($topBrick as $i => $row): $n=$i+1; ?>
                <tr class="rank-<?= $n ?>">
                    <td class="medal"><?= $n===1?'🥇':($n===2?'🥈':($n===3?'🥉':"<span style='color:var(--text-dim);font-family:var(--fd);font-size:10px;'>$n</span>")) ?></td>
                    <td class="p-name"><?= htmlspecialchars($row['username']) ?></td>
                    <td class="r s-num"><?= number_format($row['score']) ?></td>
                    <td class="r s-date"><?= date('M d', strtotime($row['played_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Dodge leaderboard -->
        <div class="lb-card game-dodge">
            <div class="lb-card-head">
                <div class="lb-card-title">🚀 ASTEROID DODGE</div>
                <div class="lb-plays" id="dodgePlaysCount"><?= $dodgePlays ?> plays</div>
            </div>
            <?php if (empty($topDodge)): ?>
            <div class="lb-empty" id="dodgeLbEmpty">No scores yet — be the first! 🎮</div>
            <?php else: ?>
            <table class="lb-tbl">
                <thead><tr>
                    <th style="width:38px">#</th>
                    <th>Player</th>
                    <th class="r">Score</th>
                    <th class="r">Date</th>
                </tr></thead>
                <tbody id="dodgeLbBody">
                <?php foreach ($topDodge as $i => $row): $n=$i+1; ?>
                <tr class="rank-<?= $n ?>">
                    <td class="medal"><?= $n===1?'🥇':($n===2?'🥈':($n===3?'🥉':"<span style='color:var(--text-dim);font-family:var(--fd);font-size:10px;'>$n</span>")) ?></td>
                    <td class="p-name"><?= htmlspecialchars($row['username']) ?></td>
                    <td class="r s-num"><?= number_format($row['score']) ?></td>
                    <td class="r s-date"><?= date('M d', strtotime($row['played_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div>
</div>

</div><!-- /.ent-wrap -->

<script>
const ME = <?= json_encode($currentUser) ?>;

/* ── Score save & leaderboard update ──────────────────────────── */
async function saveScore(game, score) {
    const prefix   = game === 'brick_breaker' ? 'brick' : 'dodge';
    const savingEl = document.getElementById(prefix + 'Saving');
    const rankEl   = document.getElementById(prefix + 'Rank');
    const bestEl   = document.getElementById(prefix === 'brick' ? 'myBrickBest' : 'myDodgeBest');

    savingEl.style.display = 'block';
    rankEl.style.display   = 'none';

    try {
        const fd = new FormData();
        fd.append('ajax_save_score', '1');
        fd.append('game', game);
        fd.append('score', score);
        const r    = await fetch(window.location.href, { method:'POST', body:fd });
        const data = await r.json();
        savingEl.style.display = 'none';

        if (data.success) {
            const ord = n => n + (['st','nd','rd'][((n%100-11)%10)-1]||'th');
            rankEl.textContent   = `🏆 Rank #${data.rank} globally  ·  Your best: ${data.best.toLocaleString()}`;
            rankEl.style.display = 'block';
            const cur = parseInt(bestEl.textContent)||0;
            if (score > cur) bestEl.textContent = score.toLocaleString();
            if (data.rank <= 10) addLeaderboardRow(prefix, score);
        }
    } catch(e) {
        savingEl.style.display = 'none';
    }
}

function addLeaderboardRow(prefix, score) {
    // Remove empty placeholder if present
    const emptyId = prefix + 'LbEmpty';
    const emptyEl = document.getElementById(emptyId);
    if (emptyEl) emptyEl.remove();

    let tbody = document.getElementById(prefix + 'LbBody');
    // If table doesn't exist yet, build it
    if (!tbody) {
        const card = document.querySelector(`.game-${prefix==='brick'?'brick':'dodge'} .lb-card`)||document.querySelectorAll('.lb-card')[prefix==='brick'?0:1];
        const tbl  = document.createElement('table');
        tbl.className = 'lb-tbl';
        tbl.innerHTML = `<thead><tr><th style="width:38px">#</th><th>Player</th><th class="r">Score</th><th class="r">Date</th></tr></thead><tbody id="${prefix}LbBody"></tbody>`;
        card.appendChild(tbl);
        tbody = document.getElementById(prefix + 'LbBody');
    }

    const today = new Date().toLocaleDateString('en-US',{month:'short',day:'numeric'});
    const tr    = document.createElement('tr');
    tr.style.animation = 'rowIn .6s ease';
    tr.innerHTML = `
        <td class="medal">⭐</td>
        <td class="p-name" style="color:var(--neon-green);">${ME}</td>
        <td class="r s-num" style="color:var(--neon-green);">${score.toLocaleString()}</td>
        <td class="r s-date">${today}</td>`;
    tbody.insertBefore(tr, tbody.firstChild);
    while (tbody.children.length > 10) tbody.removeChild(tbody.lastChild);

    // Increment plays counter
    const playsEl = document.getElementById(prefix + 'PlaysCount');
    if (playsEl) {
        const n = parseInt(playsEl.textContent)||0;
        playsEl.textContent = (n+1) + ' plays';
    }
}

/* ════════════════════════════════════════════════════════════
   BRICK BREAKER
════════════════════════════════════════════════════════════ */
(function(){
    const C   = document.getElementById('brickCanvas');
    const ctx = C.getContext('2d');
    const W=C.width, H=C.height;
    const ROWS=5, COLS=8, PAD=4;
    const BW=(W-PAD*(COLS+1))/COLS, BH=18;
    const COLORS=['#ff2d9b','#ff6d00','#ffe600','#00ff9d','#00c8ff'];

    let paddle,ball,bricks,score,alive,animReq;
    const keys={};

    function init(){
        paddle={x:W/2-40,y:H-22,w:80,h:10,speed:6};
        ball  ={x:W/2,y:H-42,vx:3.2,vy:-3.8,r:7};
        score=0; alive=false; bricks=[];
        for(let r=0;r<ROWS;r++)
            for(let c=0;c<COLS;c++)
                bricks.push({x:PAD+c*(BW+PAD),y:42+r*(BH+PAD),w:BW,h:BH,alive:true,color:COLORS[r%COLORS.length]});
        draw();
    }

    function draw(){
        ctx.fillStyle='#050810'; ctx.fillRect(0,0,W,H);

        // subtle grid
        ctx.strokeStyle='rgba(255,45,155,.025)'; ctx.lineWidth=.5;
        for(let x=0;x<W;x+=30){ctx.beginPath();ctx.moveTo(x,0);ctx.lineTo(x,H);ctx.stroke();}
        for(let y=0;y<H;y+=30){ctx.beginPath();ctx.moveTo(0,y);ctx.lineTo(W,y);ctx.stroke();}

        // bricks
        bricks.forEach(b=>{
            if(!b.alive)return;
            ctx.shadowBlur=8;ctx.shadowColor=b.color;
            ctx.fillStyle=b.color;
            ctx.beginPath();ctx.roundRect(b.x,b.y,b.w,b.h,3);ctx.fill();
            ctx.fillStyle='rgba(255,255,255,.18)';
            ctx.beginPath();ctx.roundRect(b.x+1,b.y+1,b.w-2,4,2);ctx.fill();
            ctx.shadowBlur=0;
        });

        // paddle
        const g=ctx.createLinearGradient(paddle.x,0,paddle.x+paddle.w,0);
        g.addColorStop(0,'#a020ff');g.addColorStop(1,'#ff2d9b');
        ctx.shadowBlur=14;ctx.shadowColor='#ff2d9b';
        ctx.fillStyle=g;
        ctx.beginPath();ctx.roundRect(paddle.x,paddle.y,paddle.w,paddle.h,5);ctx.fill();
        ctx.shadowBlur=0;

        // ball
        ctx.shadowBlur=20;ctx.shadowColor='#ff2d9b';
        ctx.fillStyle='#fff';
        ctx.beginPath();ctx.arc(ball.x,ball.y,ball.r,0,Math.PI*2);ctx.fill();
        ctx.shadowBlur=0;

        document.getElementById('brickBadge').textContent='SCORE: '+score;
    }

    function tick(){
        if(!alive)return;
        if(keys['ArrowLeft'])  paddle.x=Math.max(0,paddle.x-paddle.speed);
        if(keys['ArrowRight']) paddle.x=Math.min(W-paddle.w,paddle.x+paddle.speed);

        ball.x+=ball.vx; ball.y+=ball.vy;
        if(ball.x-ball.r<0){ball.x=ball.r;ball.vx*=-1;}
        if(ball.x+ball.r>W){ball.x=W-ball.r;ball.vx*=-1;}
        if(ball.y-ball.r<0){ball.y=ball.r;ball.vy*=-1;}
        if(ball.y+ball.r>H){endGame('Ball Lost 💔');return;}

        if(ball.y+ball.r>=paddle.y&&ball.y+ball.r<=paddle.y+paddle.h+4&&
           ball.x>=paddle.x-ball.r&&ball.x<=paddle.x+paddle.w+ball.r&&ball.vy>0){
            ball.vy=-Math.abs(ball.vy);
            ball.vx=(ball.x-(paddle.x+paddle.w/2))/(paddle.w/2)*5.5;
        }

        let left=0;
        bricks.forEach(b=>{
            if(!b.alive)return;
            left++;
            if(ball.x+ball.r>b.x&&ball.x-ball.r<b.x+b.w&&
               ball.y+ball.r>b.y&&ball.y-ball.r<b.y+b.h){
                b.alive=false;score+=10;left--;
                const spd=Math.sqrt(ball.vx**2+ball.vy**2);
                ball.vx=ball.vx/spd*Math.min(spd*1.015,8);
                ball.vy=-Math.abs(ball.vy/spd*Math.min(spd*1.015,8));
            }
        });
        if(left===0){endGame('You Won! 🎉');return;}

        draw();
        animReq=requestAnimationFrame(tick);
    }

    function endGame(msg){
        alive=false;cancelAnimationFrame(animReq);
        document.getElementById('brickMsg').textContent=`${msg} · Score: ${score}`;
        document.getElementById('brickBtn').textContent='▶ PLAY AGAIN';
        document.getElementById('brickBtn').onclick=startBrick;
        document.getElementById('brickRank').style.display='none';
        document.getElementById('brickOverlay').style.display='flex';
        if(score>0)saveScore('brick_breaker',score);
    }

    C.addEventListener('mousemove',e=>{
        if(!alive)return;
        const r=C.getBoundingClientRect();
        paddle.x=Math.max(0,Math.min(W-paddle.w,(e.clientX-r.left)*(W/r.width)-paddle.w/2));
    });
    document.addEventListener('keydown',e=>{keys[e.key]=true;});
    document.addEventListener('keyup',  e=>{keys[e.key]=false;});

    window.startBrick=function(){
        init();alive=true;
        document.getElementById('brickOverlay').style.display='none';
        document.getElementById('brickRank').style.display='none';
        cancelAnimationFrame(animReq);
        animReq=requestAnimationFrame(tick);
    };
    window.resetBrick=function(){
        cancelAnimationFrame(animReq);alive=false;init();
        document.getElementById('brickMsg').textContent='Break all the bricks!';
        document.getElementById('brickBtn').textContent='▶ START';
        document.getElementById('brickBtn').onclick=startBrick;
        document.getElementById('brickRank').style.display='none';
        document.getElementById('brickOverlay').style.display='flex';
    };
    init();
})();

/* ════════════════════════════════════════════════════════════
   ASTEROID DODGE
════════════════════════════════════════════════════════════ */
(function(){
    const C   = document.getElementById('dodgeCanvas');
    const ctx = C.getContext('2d');
    const W=C.width, H=C.height;

    let ship,asteroids,score,speed,spawnTimer,animReq,alive,stars;
    const dKeys={};

    function init(){
        ship={x:W/2,y:H-50,w:22,h:28,speed:5};
        asteroids=[];score=0;speed=2.5;spawnTimer=0;alive=false;
        stars=Array.from({length:80},()=>({x:Math.random()*W,y:Math.random()*H,r:Math.random()*1.5+.3,s:Math.random()*.5+.3}));
        draw();
    }

    function spawnAsteroid(){
        const sz=18+Math.random()*28;
        asteroids.push({
            x:Math.random()*(W-sz*2)+sz,y:-sz,r:sz,
            vx:(Math.random()-.5)*2,vy:speed+Math.random()*1.5,
            rot:0,rotSpeed:(Math.random()-.5)*.08,
            pts:Array.from({length:8},(_,i)=>{
                const a=i/8*Math.PI*2,j=.3+Math.random()*.7;
                return{x:Math.cos(a)*sz*j,y:Math.sin(a)*sz*j};
            })
        });
    }

    function drawShip(x,y){
        ctx.save();ctx.translate(x,y);
        ctx.shadowBlur=16;ctx.shadowColor='#ffe600';
        ctx.fillStyle='rgba(255,200,0,.35)';
        ctx.beginPath();ctx.ellipse(0,14,5,8,0,0,Math.PI*2);ctx.fill();
        ctx.fillStyle='#ffe600';ctx.shadowBlur=8;
        ctx.beginPath();ctx.moveTo(0,-14);ctx.lineTo(11,14);ctx.lineTo(0,8);ctx.lineTo(-11,14);ctx.closePath();ctx.fill();
        ctx.fillStyle='rgba(0,200,255,.85)';ctx.shadowBlur=0;
        ctx.beginPath();ctx.ellipse(0,-3,4,6,0,0,Math.PI*2);ctx.fill();
        ctx.restore();
    }

    function drawAsteroid(a){
        ctx.save();ctx.translate(a.x,a.y);ctx.rotate(a.rot);
        ctx.strokeStyle='#8ab4d4';ctx.lineWidth=1.5;
        ctx.fillStyle='rgba(40,60,90,.8)';
        ctx.shadowBlur=4;ctx.shadowColor='rgba(138,180,212,.4)';
        ctx.beginPath();
        a.pts.forEach((p,i)=>i===0?ctx.moveTo(p.x,p.y):ctx.lineTo(p.x,p.y));
        ctx.closePath();ctx.fill();ctx.stroke();
        ctx.shadowBlur=0;ctx.restore();
    }

    function draw(){
        ctx.fillStyle='#020510';ctx.fillRect(0,0,W,H);
        stars.forEach(s=>{
            s.y+=s.s;if(s.y>H){s.y=0;s.x=Math.random()*W;}
            ctx.fillStyle=`rgba(255,255,255,${.2+s.r*.3})`;
            ctx.beginPath();ctx.arc(s.x,s.y,s.r,0,Math.PI*2);ctx.fill();
        });
        asteroids.forEach(drawAsteroid);
        drawShip(ship.x,ship.y);
        document.getElementById('dodgeBadge').textContent='SCORE: '+score;
    }

    function circleRect(cx,cy,cr,rx,ry,rw,rh){
        const nx=Math.max(rx,Math.min(cx,rx+rw)),ny=Math.max(ry,Math.min(cy,ry+rh));
        return(cx-nx)**2+(cy-ny)**2<cr**2;
    }

    function tick(){
        if(!alive)return;
        score++;speed=2.5+score/300;spawnTimer++;
        if(spawnTimer>=Math.max(28,80-score/50)){spawnAsteroid();spawnTimer=0;}
        asteroids.forEach(a=>{a.x+=a.vx;a.y+=a.vy;a.rot+=a.rotSpeed;});
        asteroids=asteroids.filter(a=>a.y<H+60);
        if(dKeys['ArrowLeft'])  ship.x=Math.max(ship.w/2,ship.x-ship.speed);
        if(dKeys['ArrowRight']) ship.x=Math.min(W-ship.w/2,ship.x+ship.speed);
        if(asteroids.some(a=>circleRect(a.x,a.y,a.r,ship.x-ship.w/2,ship.y-ship.h/2,ship.w,ship.h))){
            endGame();return;
        }
        draw();animReq=requestAnimationFrame(tick);
    }

    function endGame(){
        alive=false;cancelAnimationFrame(animReq);
        document.getElementById('dodgeMsg').textContent=`Crashed! · Score: ${score}`;
        document.getElementById('dodgeBtn').textContent='▶ PLAY AGAIN';
        document.getElementById('dodgeBtn').onclick=startDodge;
        document.getElementById('dodgeRank').style.display='none';
        document.getElementById('dodgeOverlay').style.display='flex';
        if(score>0)saveScore('asteroid_dodge',score);
    }

    C.addEventListener('mousemove',e=>{
        if(!alive)return;
        const r=C.getBoundingClientRect();
        ship.x=Math.max(ship.w/2,Math.min(W-ship.w/2,(e.clientX-r.left)*(W/r.width)));
    });
    document.addEventListener('keydown',e=>{dKeys[e.key]=true;});
    document.addEventListener('keyup',  e=>{dKeys[e.key]=false;});

    window.startDodge=function(){
        init();alive=true;
        document.getElementById('dodgeOverlay').style.display='none';
        document.getElementById('dodgeRank').style.display='none';
        cancelAnimationFrame(animReq);
        animReq=requestAnimationFrame(tick);
    };
    window.resetDodge=function(){
        cancelAnimationFrame(animReq);alive=false;init();
        document.getElementById('dodgeMsg').textContent='Survive as long as you can!';
        document.getElementById('dodgeBtn').textContent='▶ START';
        document.getElementById('dodgeBtn').onclick=startDodge;
        document.getElementById('dodgeRank').style.display='none';
        document.getElementById('dodgeOverlay').style.display='flex';
    };
    init();
})();
</script>

<?php include('dashboard_footer.php'); ?>