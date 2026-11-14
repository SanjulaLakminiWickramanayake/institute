<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAuth('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $duration = trim((string) ($_POST['duration'] ?? ''));
    $overview = trim((string) ($_POST['overview'] ?? ''));
    $learningOutcomes = trim((string) ($_POST['learning_outcomes'] ?? ''));
    $status = in_array($_POST['status'] ?? 'published', ['published', 'hidden'], true) ? $_POST['status'] : 'published';

    if ($categoryId <= 0 || $title === '' || $duration === '' || $overview === '' || $learningOutcomes === '') {
        setFlash('error', 'Please complete all course fields.');
        redirect('/admin/courses.php');
    }

    $categoryCheck = db()->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
    $categoryCheck->execute([$categoryId]);
    if (!$categoryCheck->fetch()) {
        setFlash('error', 'The selected category could not be found.');
        redirect('/admin/courses.php');
    }

    $stmt = db()->prepare('INSERT INTO courses (category_id, title, duration, overview, learning_outcomes, status) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$categoryId, $title, $duration, $overview, $learningOutcomes, $status]);
    setFlash('success', 'Course published successfully.');
    redirect('/admin/courses.php');
}

$categories = db()->query('SELECT * FROM categories WHERE status = "active" ORDER BY name ASC')->fetchAll();
$courses = db()->query('SELECT c.*, cat.name AS category_name FROM courses c JOIN categories cat ON cat.id = c.category_id ORDER BY c.created_at DESC')->fetchAll();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Courses | Nexora Admin</title>
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
    .form-grid { display:grid; gap:18px; }
    .field { display:flex; flex-direction:column; gap:8px; }
    .field input, .field select, .field textarea { width:100%; padding:14px 16px; border-radius:12px; border:1px solid rgba(38,51,42,0.12); }
    .field textarea { min-height:120px; }
    .alert { padding:12px 14px; border-radius:12px; margin-bottom:18px; }
    .alert.error { background:#fdebec; color:#8b2f2f; }
    .alert.success { background:#eafaf1; color:#235f3c; }
    .table-wrap { overflow-x:auto; margin-top:20px; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:16px 18px; text-align:left; border-bottom:1px solid rgba(38,51,42,0.06); }
    th { background:#f7faf8; }
    .status-pill { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:600; }
    .status-published { background:#dff7ea; color:#1b7a49; }
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
        <a href="courses.php" class="active">Courses</a>
        <a href="categories.php">Categories</a>
        <a href="intakes.php">Intakes</a>
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
        <h1>Course Management</h1>
      </div>

      <?php if ($flash): ?>
        <div class="alert <?= e($flash['type']); ?>"><?= e($flash['message']); ?></div>
      <?php endif; ?>

      <div class="panel">
        <form method="post" action="courses.php" class="form-grid">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>" />
          <div class="field">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" required>
              <option value="">Select category</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id']; ?>"><?= e($category['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="title">Course title</label>
            <input id="title" name="title" type="text" required />
          </div>
          <div class="field">
            <label for="duration">Duration</label>
            <input id="duration" name="duration" type="text" placeholder="e.g. 12 Months" required />
          </div>
          <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
              <option value="published">Published</option>
              <option value="hidden">Hidden</option>
            </select>
          </div>
          <div class="field">
            <label for="overview">Overview</label>
            <textarea id="overview" name="overview" required></textarea>
          </div>
          <div class="field">
            <label for="learning_outcomes">Learning outcomes</label>
            <textarea id="learning_outcomes" name="learning_outcomes" required></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Create course</button>
        </form>
      </div>

      <div class="table-wrap panel" style="margin-top:24px;">
        <table>
          <thead>
            <tr><th>Course</th><th>Category</th><th>Duration</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($courses as $course): ?>
              <tr>
                <td><?= e($course['title']); ?></td>
                <td><?= e($course['category_name']); ?></td>
                <td><?= e($course['duration']); ?></td>
                <td><span class="status-pill status-<?= e($course['status']); ?>"><?= e(ucfirst($course['status'])); ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</body>
</html>
