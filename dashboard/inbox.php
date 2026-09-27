<?php
require_once __DIR__ . '/bootstrap.php';
require_login();
$business = current_business($db);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Inbox — AI Inbox</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
 body{background:#f4f5f7}
 #convList{max-height:calc(100vh - 140px);overflow-y:auto}
 .conv-item{cursor:pointer}
 .conv-item.active{background:#e7f1ff}
 #thread{height:calc(100vh - 260px);overflow-y:auto;background:#fff}
 .bubble{max-width:75%;padding:.5rem .75rem;border-radius:.9rem;margin-bottom:.5rem;white-space:pre-wrap}
 .bubble.in{background:#e9ecef;margin-right:auto}
 .bubble.out{background:#0d6efd;color:#fff;margin-left:auto}
 .bubble.agent{background:#198754;color:#fff;margin-left:auto}
 .bubble.echo{background:#6c757d;color:#fff;margin-left:auto}
</style></head><body>
<?php include __DIR__ . '/_nav.php'; ?>
<main class="container-fluid py-3" style="max-width:1100px">
<?php if (!$business): ?>
 <div class="alert alert-warning">No business set up yet. Go to <a href="settings.php">Settings</a> to create one and add a Facebook Page.</div>
<?php else: ?>
<div class="row g-3">
 <div class="col-12 col-md-4">
  <div class="card shadow-sm"><div class="card-header fw-semibold">Conversations</div>
   <div id="convList" class="list-group list-group-flush"></div>
  </div>
 </div>
 <div class="col-12 col-md-8">
  <div class="card shadow-sm">
   <div class="card-header d-flex justify-content-between align-items-center">
    <span id="threadTitle" class="fw-semibold text-muted">Select a conversation</span>
    <button id="resumeBtn" class="btn btn-sm btn-outline-success d-none">Resume AI</button>
   </div>
   <div id="thread" class="card-body"></div>
   <div class="card-footer">
    <form id="replyForm" class="d-flex gap-2">
     <input type="hidden" id="csrf" value="<?= csrf() ?>">
     <input id="replyText" class="form-control" placeholder="Type a manual reply (pauses AI for 12h)…" disabled>
     <button class="btn btn-dark" disabled>Send</button>
    </form>
   </div>
  </div>
 </div>
</div>
<?php endif ?>
</main>
<script>
let active = null; // {page_id, psid}

function esc(s){ const d=document.createElement('div'); d.innerText = s ?? ''; return d.innerHTML; }

async function loadConversations(){
  const res = await fetch('api/conversations.php');
  const rows = await res.json();
  const list = document.getElementById('convList');
  list.innerHTML = rows.length ? '' : '<div class="p-3 text-muted">No conversations yet.</div>';
  rows.forEach(r => {
    const isActive = active && active.page_id===r.page_id && active.psid===r.psid;
    const div = document.createElement('div');
    div.className = 'list-group-item conv-item' + (isActive ? ' active' : '');
    div.innerHTML = `<div class="d-flex justify-content-between"><strong>${esc(r.customer_name || 'Customer')}</strong><small class="text-muted">${esc(r.ago)}</small></div>
      <div class="small text-muted text-truncate">${esc(r.last_message || '')}</div>
      <div class="small">${esc(r.page_name || r.page_id)} ${r.is_human ? '<span class="badge text-bg-warning">Manual</span>' : ''} ${r.lead_status ? '<span class="badge text-bg-light">'+esc(r.lead_status)+'</span>' : ''}</div>`;
    div.onclick = () => selectConversation(r);
    list.appendChild(div);
  });
}

function selectConversation(r){
  active = {page_id: r.page_id, psid: r.psid, page_name: r.page_name, customer_name: r.customer_name, is_human: r.is_human};
  document.getElementById('threadTitle').textContent = (r.customer_name || 'Customer') + ' — ' + (r.page_name || r.page_id);
  document.getElementById('replyText').disabled = false;
  document.querySelector('#replyForm button').disabled = false;
  document.getElementById('resumeBtn').classList.toggle('d-none', !r.is_human);
  loadThread();
  loadConversations();
}

async function loadThread(){
  if (!active) return;
  const res = await fetch(`api/messages.php?page_id=${encodeURIComponent(active.page_id)}&psid=${encodeURIComponent(active.psid)}`);
  const rows = await res.json();
  const el = document.getElementById('thread');
  const atBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 20;
  el.innerHTML = rows.map(m => `<div class="bubble ${m.direction}">${esc(m.body)}</div>`).join('');
  if (atBottom || el.dataset.first !== '1') { el.scrollTop = el.scrollHeight; el.dataset.first = '1'; }
}

document.getElementById('replyForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  if (!active) return;
  const text = document.getElementById('replyText').value.trim();
  if (!text) return;
  document.getElementById('replyText').value = '';
  await fetch('api/send.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({csrf: document.getElementById('csrf').value, page_id: active.page_id, psid: active.psid, text})});
  active.is_human = true;
  document.getElementById('resumeBtn').classList.remove('d-none');
  loadThread();
});

document.getElementById('resumeBtn').addEventListener('click', async () => {
  if (!active) return;
  await fetch('api/resume.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({csrf: document.getElementById('csrf').value, page_id: active.page_id, psid: active.psid})});
  active.is_human = false;
  document.getElementById('resumeBtn').classList.add('d-none');
});

loadConversations();
setInterval(loadConversations, 6000);
setInterval(loadThread, 4000);
</script>
</body></html>
