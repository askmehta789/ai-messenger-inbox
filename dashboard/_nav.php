<nav class="navbar bg-white border-bottom sticky-top"><div class="container-fluid" style="max-width:1100px">
 <span class="navbar-brand fw-semibold">🤖 AI Inbox</span>
 <form class="d-flex align-items-center gap-2 flex-grow-1 mx-3" style="max-width:280px">
  <select name="business_id" class="form-select form-select-sm" onchange="this.form.submit()">
   <?php foreach (all_businesses($db) as $b): ?>
    <option value="<?= (int)$b['id'] ?>" <?= $business && $business['id'] == $b['id'] ? 'selected' : '' ?>><?= h($b['name']) ?></option>
   <?php endforeach ?>
  </select>
 </form>
 <div class="d-flex gap-2">
  <a class="btn btn-sm <?= basename($_SERVER['PHP_SELF']) === 'inbox.php' ? 'btn-dark' : 'btn-outline-secondary' ?>" href="inbox.php">Inbox</a>
  <a class="btn btn-sm <?= basename($_SERVER['PHP_SELF']) === 'leads.php' ? 'btn-dark' : 'btn-outline-secondary' ?>" href="leads.php">Leads</a>
  <a class="btn btn-sm <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'btn-dark' : 'btn-outline-secondary' ?>" href="settings.php">Settings</a>
  <a class="btn btn-sm btn-outline-danger" href="index.php?logout=1">Log out</a>
 </div>
</div></nav>
