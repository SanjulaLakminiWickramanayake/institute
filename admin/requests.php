<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAuth('admin');

$statusFilter = $_GET['status'] ?? 'all';
$query = 'SELECT a.id, a.full_name, a.email, a.status, a.created_at, c.title AS course_name, i.name AS intake_name
          FROM applications a
          JOIN courses c ON c.id = a.course_id
          JOIN intakes i ON i.id = a.intake_id';
$params = [];
if ($statusFilter !== 'all') {
    $query .= ' WHERE a.status = ?';
    $params[] = $statusFilter;
}
$query .= ' ORDER BY a.created_at DESC';

$stmt = db()->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Registration Requests | Nexora Admin</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    body { background: #f4f7f3; margin:0; }
    .admin-shell { display:grid; grid-template-columns:260px 1fr; min-height:100vh; }
    .admin-sidebar { background:#1d2d24; color:#fff; padding:24px 18px; }
    .admin-brand { display:flex; align-items:center; gap:12px; margin-bottom:30px; }
    .admin-sidebar nav a { display:block; color: rgba(255,255,255,0.82); padding:12px 14px; border-radius:10px; margin-bottom:8px; text-decoration:none; }
    .admin-sidebar nav a.active, .admin-sidebar nav a:hover { background: rgba(255,255,255,0.12); color:#fff; }
    .admin-main { padding:28px; }
    .panel { background:#fff; border:1px solid rgba(38,51,42,0.06); border-radius:18px; padding:24px; box-shadow:0 16px 30px rgba(38,51,42,0.04); }
    .filters { display:flex; gap:12px; flex-wrap:wrap; margin:12px 0 18px; }
    .filter-btn { display:inline-flex; align-items:center; justify-content:center; padding:10px 16px; border-radius:999px; text-decoration:none; background:#eef3ef; color:#1d2d24; font-weight:600; }
    .filter-btn.active { background:#1d2d24; color:#fff; }
    .status-pill { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:600; }
    .status-pending_review, .status-pending_verification { background:#fff4d5; color:#9f6a00; }
    .status-approved { background:#dff7ea; color:#1b7a49; }
    .status-rejected { background:#fbe8e5; color:#a33f2f; }
    .alert { padding:12px 14px; border-radius:12px; margin-bottom:18px; }
    .alert.error { background:#fdebec; color:#8b2f2f; }
    .alert.success { background:#eafaf1; color:#235f3c; }
    table { width:100%; border-collapse: collapse; }
    th, td { padding:16px 18px; text-align:left; border-bottom:1px solid rgba(38,51,42,0.06); }
    th { background:#f7faf8; }
    @media (max-width:900px){ .admin-shell{grid-template-columns:1fr;} .admin-sidebar{padding-bottom:10px;} }
  </style>
</head>
<body>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <div class="admin-brand">
        <img src="../images/logo/logo.jpg" alt="Nexora" style="width:42px;height:42px;border-radius:12px;" />
        <div><strong>Nexora</strong><br /><small>Admin</small></div>
      </div>
      <nav>
        <a href="index.php">Dashboard</a>
        <a href="courses.php">Courses</a>
        <a href="categories.php">Categories</a>
        <a href="intakes.php">Intakes</a>
        <a href="requests.php" class="active">Registration Requests</a>
        <a href="students.php">Students</a>
        <a href="messages.php">Messages</a>
        <a href="email-log.php">Email Log</a>
        <a href="../sign-out.php">Sign Out</a>
      </nav>
    </aside>
    <main class="admin-main">
      <div class="section-heading left" style="margin-bottom:18px;">
        <span class="eyebrow">APPLICATIONS</span>
        <h1>Registration Requests</h1>
      </div>

      <?php if ($flash): ?>
        <div class="alert <?= e($flash['type']); ?>"><?= e($flash['message']); ?></div>
      <?php endif; ?>

      <div class="panel">
        <div class="filters">
          <a class="filter-btn <?= $statusFilter === 'all' ? 'active' : ''; ?>" href="requests.php?status=all">All</a>
          <a class="filter-btn <?= $statusFilter === 'pending_review' ? 'active' : ''; ?>" href="requests.php?status=pending_review">Pending review</a>
          <a class="filter-btn <?= $statusFilter === 'pending_verification' ? 'active' : ''; ?>" href="requests.php?status=pending_verification">Pending verification</a>
          <a class="filter-btn <?= $statusFilter === 'approved' ? 'active' : ''; ?>" href="requests.php?status=approved">Approved</a>
          <a class="filter-btn <?= $statusFilter === 'rejected' ? 'active' : ''; ?>" href="requests.php?status=rejected">Rejected</a>
        </div>

        <table>
          <thead>
            <tr><th>Applicant</th><th>Email</th><th>Course</th><th>Intake</th><th>Status</th><th>Submitted</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php if (empty($requests)): ?>
              <tr><td colspan="7">No requests found.</td></tr>
            <?php else: ?>
              <?php foreach ($requests as $request): ?>
                <tr>
                  <td><?= e($request['full_name']); ?></td>
                  <td><?= e($request['email']); ?></td>
                  <td><?= e($request['course_name']); ?></td>
                  <td><?= e($request['intake_name']); ?></td>
                  <td><span class="status-pill status-<?= e($request['status']); ?>"><?= e(str_replace('_', ' ', ucfirst($request['status']))); ?></span></td>
                  <td><?= e(date('Y-m-d', strtotime($request['created_at']))); ?></td>
                  <td><a href="request.php?id=<?= (int) $request['id']; ?>" class="btn btn-secondary small">Review</a></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</body>
</html>
