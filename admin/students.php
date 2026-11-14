<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAuth('admin');

$students = db()->query('SELECT s.*, c.title AS course_name, i.name AS intake_name FROM students s JOIN courses c ON c.id = s.course_id JOIN intakes i ON i.id = s.intake_id ORDER BY s.created_at DESC')->fetchAll();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Students | Nexora Admin</title>
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
    .status-pill { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:600; }
    .status-active { background:#dff7ea; color:#1b7a49; }
    .status-inactive { background:#fff4d5; color:#9f6a00; }
    .status-suspended { background:#fbe8e5; color:#a33f2f; }
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
        <a href="students.php" class="active">Students</a>
        <a href="messages.php">Messages</a>
        <a href="email-log.php">Email Log</a>
        <a href="../sign-out.php">Sign Out</a>
      </nav>
    </aside>
    <main class="admin-main">
      <div class="section-heading left" style="margin-bottom:18px;">
        <span class="eyebrow">PEOPLE</span>
        <h1>Student Records</h1>
      </div>

      <div class="panel">
        <table>
          <thead>
            <tr><th>Student Number</th><th>Name</th><th>Email</th><th>Course</th><th>Intake</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($students as $student): ?>
              <tr>
                <td><?= e($student['student_number']); ?></td>
                <td><?= e($student['full_name']); ?></td>
                <td><?= e($student['email']); ?></td>
                <td><?= e($student['course_name']); ?></td>
                <td><?= e($student['intake_name']); ?></td>
                <td><span class="status-pill status-<?= e($student['status']); ?>"><?= e(ucfirst($student['status'])); ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</body>
</html>
