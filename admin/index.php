<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAuth('admin');

$categoriesCount = (int) db()->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$pendingStmt = db()->prepare('SELECT COUNT(*) FROM applications WHERE status = ?');
$pendingStmt->execute(['pending_review']);
$pendingCount = (int) $pendingStmt->fetchColumn();

$approvedStmt = db()->prepare('SELECT COUNT(*) FROM applications WHERE status = ?');
$approvedStmt->execute(['approved']);
$approvedCount = (int) $approvedStmt->fetchColumn();

$rejectedStmt = db()->prepare('SELECT COUNT(*) FROM applications WHERE status = ?');
$rejectedStmt->execute(['rejected']);
$rejectedCount = (int) $rejectedStmt->fetchColumn();

$publishedStmt = db()->prepare('SELECT COUNT(*) FROM courses WHERE status = ?');
$publishedStmt->execute(['published']);
$publishedCount = (int) $publishedStmt->fetchColumn();

$requests = db()->query('SELECT a.id, a.full_name, c.title AS course_name, i.name AS intake_name, a.created_at, a.status FROM applications a JOIN courses c ON c.id = a.course_id JOIN intakes i ON i.id = a.intake_id ORDER BY a.created_at DESC LIMIT 20')->fetchAll();
$user = currentUser();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard | Nexora Institute</title>
  <link rel="icon" type="image/jpeg" href="../images/logo/logo.jpg" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../css/style.css" />
  <script src="../js/script.js" defer></script>
  <style>
    body { background: #f4f7f3; }
    .admin-shell { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
    .admin-sidebar { background: #1d2d24; color: #fff; padding: 24px 18px; }
    .admin-brand { display:flex; align-items:center; gap:12px; margin-bottom:30px; }
    .admin-sidebar nav a { display:block; color: rgba(255,255,255,0.82); padding: 12px 14px; border-radius:10px; margin-bottom:8px; text-decoration:none; }
    .admin-sidebar nav a.active, .admin-sidebar nav a:hover { background: rgba(255,255,255,0.12); color:#fff; }
    .admin-main { padding: 28px; }
    .stats-grid.admin-stats { grid-template-columns: repeat(5, minmax(0,1fr)); }
    .table-wrap { overflow-x:auto; background:#fff; border:1px solid rgba(38,51,42,0.06); border-radius:18px; box-shadow:0 16px 30px rgba(38,51,42,0.04); }
    table { width:100%; border-collapse: collapse; }
    th, td { text-align:left; padding: 16px 18px; border-bottom:1px solid rgba(38,51,42,0.06); }
    th { background:#f7faf8; }
    .status-pill { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:600; }
    .status-pending { background: #fff4d5; color: #9f6a00; }
    .status-approved { background: #dff7ea; color: #1b7a49; }
    .status-rejected { background: #fbe8e5; color: #a33f2f; }
    .status-verification { background: #e6f0ff; color: #1f5dbb; }
    .alert { padding:14px 16px; border-radius:12px; margin-bottom:18px; }
    .alert.error { background:#fdebec; color:#8b2f2f; }
    .alert.success { background:#eafaf1; color:#235f3c; }
    .topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; }
    @media (max-width: 900px) { .admin-shell { grid-template-columns:1fr; } .admin-sidebar { padding-bottom: 10px; } .stats-grid.admin-stats { grid-template-columns: repeat(2,minmax(0,1fr)); } }
  </style>
</head>
<body data-page="admin">
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <div class="admin-brand">
        <img src="../images/logo/logo.jpg" alt="Nexora logo" style="width:42px;height:42px;border-radius:12px;" />
        <div>
          <strong>Nexora</strong><br />
          <small>Admin</small>
        </div>
      </div>
      <nav>
        <a href="index.php" class="active">Dashboard</a>
        <a href="courses.php">Courses</a>
        <a href="categories.php">Categories</a>
        <a href="intakes.php">Intakes</a>
        <a href="requests.php">Registration Requests</a>
        <a href="students.php">Students</a>
        <a href="messages.php">Messages</a>
        <a href="email-log.php">Email Log</a>
        <a href="email-test.php">Test Email Configuration</a>
        <a href="change-password.php">Change Password</a>
        <a href="../sign-out.php">Sign Out</a>
      </nav>
    </aside>
    <main class="admin-main">
      <div class="topbar">
        <div>
          <span class="eyebrow">ADMIN DASHBOARD</span>
          <h1 style="margin:0;">Welcome, <?= e($user['full_name'] ?? 'Admin'); ?></h1>
        </div>
        <a href="../sign-out.php" class="btn btn-secondary small">Sign Out</a>
      </div>

      <?php if ($flash): ?>
        <div class="alert <?= e($flash['type']); ?>"><?= e($flash['message']); ?></div>
      <?php endif; ?>

      <div class="stats-grid admin-stats reveal fade-up">
        <div class="stat-card"><strong><?= $categoriesCount; ?></strong><span>Categories</span></div>
        <div class="stat-card"><strong><?= $pendingCount; ?></strong><span>Pending Requests</span></div>
        <div class="stat-card"><strong><?= $approvedCount; ?></strong><span>Approved Requests</span></div>
        <div class="stat-card"><strong><?= $rejectedCount; ?></strong><span>Rejected Requests</span></div>
        <div class="stat-card"><strong><?= $publishedCount; ?></strong><span>Active Courses</span></div>
      </div>

      <section style="margin-top:30px;">
        <div class="section-heading left" style="margin-bottom:18px;">
          <span class="eyebrow">REGISTRATION REQUESTS</span>
          <h2>Recent Requests</h2>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Applicant</th>
                <th>Course</th>
                <th>Intake</th>
                <th>Submitted</th>
                <th>Status</th>
                <th>Review</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($requests)): ?>
                <tr><td colspan="6">No registration requests found.</td></tr>
              <?php else: ?>
                <?php foreach ($requests as $request): ?>
                  <tr>
                    <td><?= e($request['full_name']); ?></td>
                    <td><?= e($request['course_name']); ?></td>
                    <td><?= e($request['intake_name']); ?></td>
                    <td><?= e(date('Y-m-d', strtotime($request['created_at']))); ?></td>
                    <td>
                      <?php
                        $statusClass = 'status-pending';
                        $statusLabel = ucfirst(str_replace('_', ' ', $request['status']));
                        if ($request['status'] === 'approved') { $statusClass = 'status-approved'; }
                        if ($request['status'] === 'rejected') { $statusClass = 'status-rejected'; }
                        if ($request['status'] === 'pending_verification') { $statusClass = 'status-verification'; }
                      ?>
                      <span class="status-pill <?= $statusClass; ?>"><?= e($statusLabel); ?></span>
                    </td>
                    <td><a href="request.php?id=<?= (int) $request['id']; ?>" class="btn btn-secondary small">Review</a></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
