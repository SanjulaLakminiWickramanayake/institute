<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAuth('admin');

$messages = db()->query('SELECT * FROM messages ORDER BY created_at DESC')->fetchAll();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Messages | Nexora Admin</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    body { background:#f4f7f3; margin:0; }
    .admin-shell { display:grid; grid-template-columns:260px 1fr; min-height:100vh; }
    .admin-sidebar { background:#1d2d24; color:#fff; padding:24px 18px; }
    .admin-brand { display:flex; align-items:center; gap:12px; margin-bottom:30px; }
    .admin-sidebar nav a { display:block; color: rgba(255,255,255,0.82); padding:12px 14px; border-radius:10px; margin-bottom:8px; text-decoration:none; }
    .admin-sidebar nav a.active, .admin-sidebar nav a:hover { background: rgba(255,255,255,0.12); color:#fff; }
    .admin-main { padding:28px; }
    .panel { background:#fff; border:1px solid rgba(38,51,42,0.06); border-radius:18px; padding:24px; box-shadow:0 16px 30px rgba(38,51,42,0.04); }
    table { width:100%; border-collapse:collapse; }
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
        <a href="requests.php">Registration Requests</a>
        <a href="students.php">Students</a>
        <a href="messages.php" class="active">Messages</a>
        <a href="email-log.php">Email Log</a>
        <a href="../sign-out.php">Sign Out</a>
      </nav>
    </aside>
    <main class="admin-main">
      <div class="section-heading left" style="margin-bottom:18px;">
        <span class="eyebrow">COMMUNICATIONS</span>
        <h1>Student Messages</h1>
      </div>

      <?php if ($flash): ?>
        <div class="alert <?= e($flash['type']); ?>"><?= e($flash['message']); ?></div>
      <?php endif; ?>

      <div class="panel">
        <table>
          <thead>
            <tr><th>Sender</th><th>Email</th><th>Subject</th><th>Message</th><th>Date</th></tr>
          </thead>
          <tbody>
            <?php foreach ($messages as $message): ?>
              <tr>
                <td><?= e($message['sender_name']); ?></td>
                <td><?= e($message['sender_email']); ?></td>
                <td><?= e($message['subject']); ?></td>
                <td><?= e(substr($message['message'], 0, 120)); ?><?= strlen($message['message']) > 120 ? '...' : ''; ?></td>
                <td><?= e(date('Y-m-d', strtotime($message['created_at']))); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</body>
</html>
