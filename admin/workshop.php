<?php
require 'includes/auth.php';
require_once __DIR__ . '/../config/db.php';
include 'includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="title">QUẢN LÝ WORKSHOP</div>
  </div>
</div>

<div class="dash-row workshop-hub-row" style="flex-wrap:wrap;">
  <div class="panel workshop-hub-card" style="flex:1;min-width:240px;display:flex;flex-direction:column;gap:12px;">
    <h3 style="margin:0;color:#6b3f3f;">1. Quản lý chủ đề</h3>
    <a class="add-btn" href="workshop_topics.php" style="align-self:flex-start;text-decoration:none;display:inline-block;">Mở →</a>
  </div>
  <div class="panel workshop-hub-card" style="flex:1;min-width:240px;display:flex;flex-direction:column;gap:12px;">
    <h3 style="margin:0;color:#6b3f3f;">2. Quản lý lịch</h3>
    <a class="add-btn" href="workshop_schedule.php" style="align-self:flex-start;text-decoration:none;display:inline-block;">Mở →</a>
  </div>
  <div class="panel workshop-hub-card" style="flex:1;min-width:240px;display:flex;flex-direction:column;gap:12px;">
    <h3 style="margin:0;color:#6b3f3f;">3. Lịch sử workshop</h3>
    <a class="add-btn" href="workshop_history.php" style="align-self:flex-start;text-decoration:none;display:inline-block;">Mở →</a>
  </div>
</div>

<?php include 'includes/footer.php';
