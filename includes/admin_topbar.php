<?php
// Calling convention mirrors admin_sidebar.php's $current: set $topbarTitle
// (and optionally $topbarBreadcrumb / $topbarActions) immediately before
// this include. Defaults keep the include safe if a caller forgets.
$topbarTitle = $topbarTitle ?? 'Admin';
$topbarBreadcrumb = $topbarBreadcrumb ?? $topbarTitle;
$topbarActions = $topbarActions ?? '';
?>
<div class="d-flex align-items-center justify-content-between bg-body border-bottom px-4 py-3 admin-topbar">
  <div class="d-flex align-items-center flex-grow-1 overflow-hidden admin-topbar-title-group">
    <button class="btn btn-sm btn-outline-secondary d-lg-none me-2 flex-shrink-0"
            data-bs-toggle="offcanvas"
            data-bs-target="#adminSidebar"
            aria-controls="adminSidebar"
            aria-label="Toggle sidebar">
      <i class="fas fa-bars" aria-hidden="true"></i>
    </button>
    <div class="w-100">
      <h1 class="h4 mb-0 text-truncate"><?= htmlspecialchars($topbarTitle, ENT_QUOTES) ?></h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($topbarBreadcrumb, ENT_QUOTES) ?></li>
        </ol>
      </nav>
    </div>
  </div>
  <div class="d-flex align-items-center flex-shrink-0 ms-3">
    <!-- Theme toggle (System Enhancements initiative, Step 8b) — static
         markup here rather than via $topbarActions, since that slot is
         per-page and a global control must not depend on every caller
         remembering to set it. js/theme.js owns its icon/aria state. -->
    <button type="button" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center me-3 js-theme-toggle" id="adminThemeToggle" style="width:44px;height:44px;" aria-label="Switch to dark mode" aria-pressed="false">
      <i class="fas fa-moon" aria-hidden="true"></i>
    </button>
    <span class="fw-bold font-poppins d-none d-sm-inline me-3">Hello, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES) ?>!</span>
    <?php if ($topbarActions !== ''): ?>
      <div class="ms-auto"><?= $topbarActions ?></div>
    <?php endif; ?>
  </div>
</div>
