<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin','instructor']);
$user = current_user();

$course_id = intval($_GET['course_id'] ?? 0);
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

// fetch courses depending on role
if ($user['role'] === 'admin') {
    $courses = $pdo->query('SELECT id,title FROM courses ORDER BY title')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT id,title FROM courses WHERE instructor_id = ? ORDER BY title');
    $stmt->execute([$user['id']]);
    $courses = $stmt->fetchAll();
}

?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Enrollment & Progress Reports</title>
<link rel="stylesheet" href="/assets/css/style.css"></head><body>
<?php require_once __DIR__ . '/../includes/topnav.php'; ?>
<h1>Enrollment & Progress Reports</h1>

<form method="get">
  <label>Course:
    <select name="course_id">
      <option value="">-- All --</option>
      <?php foreach ($courses as $c): ?>
        <option value="<?php echo $c['id']; ?>" <?php echo ($course_id==$c['id'])?'selected':''; ?>><?php echo htmlspecialchars($c['title']); ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>From: <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>"></label>
  <label>To: <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>"></label>
  <button>Filter</button>
</form>

<h2>Export</h2>
<form method="post" action="/export_enrollments_csv.php">
  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
  <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course_id); ?>">
  <input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>">
  <input type="hidden" name="to" value="<?php echo htmlspecialchars($to); ?>">
  <button type="submit">Export CSV</button>
</form>

<form method="post" action="/export_progress_pdf.php" style="margin-top:8px">
  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
  <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course_id); ?>">
  <input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>">
  <input type="hidden" name="to" value="<?php echo htmlspecialchars($to); ?>">
  <button type="submit">Export PDF</button>
</form>

<p><a href="/dashboard.php">Back</a></p>
</body></html>
