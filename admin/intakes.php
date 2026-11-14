<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAuth('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name = trim((string) ($_POST['name'] ?? ''));
    $status = in_array($_POST['status'] ?? 'active', ['active', 'hidden'], true) ? $_POST['status'] : 'active';

    if ($name === '') {
        setFlash('error', 'Intake name is required.');
        redirect('/admin/intakes.php');
    }

    $stmt = db()->prepare('INSERT INTO intakes (name, status) VALUES (?, ?)');
    $stmt->execute([$name, $status]);
    setFlash('success', 'Intake added successfully.');
    redirect('/admin/intakes.php');
}

$intakes = db()->query('SELECT * FROM intakes ORDER BY created_at DESC')->fetchAll();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Intakes | Nexora Admin</title>
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
    .field { display:flex; flex-direction:column; gap:8px; }
    .field input, .field select { width:100%; padding:14px 16px; border-radius:12px; border:1px solid rgba(38,51,42,0.12); }
    .alert { padding:12px 14px; border-radius:12px; margin-bottom:18px; }
    .alert.error { background:#fdebec; color:#8b2f2f; }
    .alert.success { background:#eafaf1; color:#235f3c; }
    .table-wrap { overflow-x:auto; margin-top:20px; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:16px 18px; text-align:left; border-bottom:1px solid rgba(38,51,42,0.06); }
    th { background:#f7faf8; }
    .status-pill { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:600; }
    .status-active { background:#dff7ea; color:#1b7a49; }
    .status-hidden { background:#f4f1eb; color:#5c5a54; }
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
        <a href="intakes.php" class="active">Intakes</a>
        <a href="requests.php">Registration Requests</a>
        <a href="students.php">Students</a>
        <a href="messages.php">Messages</a>
        <a href="email-log.php">Email Log</a>
        <a href="../sign-out.php">Sign Out</a>
      </nav>
    </aside>
    <main class="admin-main">
      <div class="section-heading left" style="margin-bottom:18px;">
        <span class="eyebrow">MANAGEMENT</span>
        <h1>Available Intakes</h1>
      </div>

      <?php if ($flash): ?>
        <div class="alert <?= e($flash['type']); ?>"><?= e($flash['message']); ?></div>
      <?php endif; ?>

      <div class="panel">
        <form method="post" action="intakes.php" style="display:grid;gap:18px;">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>" />
          <div class="field">
            <label for="name">Intake name</label>
            <input id="name" name="name" type="text" placeholder="e.g. January 2026" required />
          </div>
          <div class="field">
            <label for="status">Visibility</label>
            <select id="status" name="status">
              <option value="active">Active</option>
              <option value="hidden">Hidden</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">Add intake</button>
        </form>
      </div>

      <div class="table-wrap panel" style="margin-top:24px;">
        <table>
          <thead>
            <tr><th>ID</th><th>Name</th><th>Status</th><th>Created</th></tr>
          </thead>
          <tbody>
            <?php foreach ($intakes as $intake): ?>
              <tr>
                <td><?= (int) $intake['id']; ?></td>
                <td><?= e($intake['name']); ?></td>
                <td><span class="status-pill status-<?= e($intake['status']); ?>"><?= e(ucfirst($intake['status'])); ?></span></td>
                <td><?= e(date('Y-m-d', strtotime($intake['created_at']))); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</body>
</html>
