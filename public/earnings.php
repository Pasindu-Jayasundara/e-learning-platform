<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin','instructor']);
$user = current_user();

$course_id = intval($_GET['course_id'] ?? 0);
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

// fetch courses available to this user
if ($user['role'] === 'admin') {
    $courses = $pdo->query('SELECT id,title FROM courses ORDER BY title')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT id,title FROM courses WHERE instructor_id = ? ORDER BY title');
    $stmt->execute([$user['id']]);
    $courses = $stmt->fetchAll();
}

// build aggregation query
$sql = 'SELECT c.id as course_id, c.title as course_title, u.id as instructor_id, u.name as instructor_name, SUM(p.amount) as total_earned, COUNT(p.id) as payments_count FROM payments p JOIN courses c ON p.course_id = c.id JOIN users u ON c.instructor_id = u.id WHERE p.status = "completed"';
$params = [];
if ($course_id) { $sql .= ' AND c.id = ?'; $params[] = $course_id; }
if ($from) { $sql .= ' AND p.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to) { $sql .= ' AND p.created_at <= ?'; $params[] = $to . ' 23:59:59'; }
if ($user['role'] === 'instructor') { $sql .= ' AND c.instructor_id = ?'; $params[] = $user['id']; }
$sql .= ' GROUP BY c.id, u.id ORDER BY total_earned DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// totals for summary
$sumPayments = 0; $sumAmount = 0.0;
foreach ($rows as $r) { $sumPayments += intval($r['payments_count']); $sumAmount += floatval($r['total_earned']); }

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Earnings - E-Learning Platform</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    .filters-grid { display:grid; grid-template-columns: 1fr 1fr 1fr auto; gap:12px; align-items:end; }
    @media (max-width: 720px) { .filters-grid { grid-template-columns: 1fr 1fr; } }
    .form-field label { display:block; font-weight:600; margin-bottom:6px; }
    table.earnings-table { width:100%; border-collapse: collapse; }
    table.earnings-table th, table.earnings-table td { padding:10px 12px; border-bottom:1px solid var(--border-color); text-align:left; }
    table.earnings-table th { background: var(--surface-2); }
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <main>
    <div class="container">
      <div class="page-header fade-in">
        <h1><?php echo $user['role']==='admin' ? 'Platform Earnings' : 'Instructor Earnings'; ?></h1>
        <p class="muted">Filter and export earnings by course and date range.</p>
      </div>

      <!-- Filters -->
      <div class="card fade-in" style="margin-bottom: 24px;">
        <div class="card-header"><h2>Filters</h2></div>
        <div class="card-body">
          <form method="get" style="margin:0;">
            <div class="filters-grid">
              <div class="form-field">
                <label for="course_id">Course</label>
                <select id="course_id" name="course_id">
                  <option value="">All courses</option>
                  <?php foreach ($courses as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($course_id==$c['id'])?'selected':''; ?>><?php echo htmlspecialchars($c['title']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-field">
                <label for="from">From</label>
                <input id="from" type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
              </div>
              <div class="form-field">
                <label for="to">To</label>
                <input id="to" type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
              </div>
              <div class="form-field" style="display:flex; gap:8px;">
                <button type="submit" class="btn primary">Apply</button>
                <?php if (!empty($rows)): ?>
                  <a class="btn secondary" href="/export_earnings_csv.php?course_id=<?php echo urlencode((string)$course_id); ?>&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>">Export CSV</a>
                  <a class="btn secondary" href="/export_earnings_pdf.php?course_id=<?php echo urlencode((string)$course_id); ?>&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>">Export PDF</a>
                <?php endif; ?>
              </div>
            </div>
          </form>
        </div>
      </div>

      <?php if (empty($rows)): ?>
        <div class="card fade-in" style="padding: 24px;">
          <p class="muted">No earnings found for the selected filters.</p>
        </div>
      <?php else: ?>
        <!-- Summary stats -->
        <div class="stats-grid fade-in" style="margin-bottom: 16px;">
          <div class="stat-card"><div class="stat-card-label">Payments</div><div class="stat-card-value"><?php echo number_format($sumPayments); ?></div></div>
          <div class="stat-card" style="border-left-color: var(--accent-color);"><div class="stat-card-label">Total Earned</div><div class="stat-card-value">$<?php echo number_format($sumAmount,2); ?></div></div>
        </div>

        <!-- Results table -->
        <div class="card fade-in">
          <div class="card-header"><h2>Results</h2></div>
          <div class="card-body" style="overflow-x:auto;">
            <table class="earnings-table">
              <thead>
                <tr><th>Course</th><th>Instructor</th><th>Payments</th><th>Total Earned</th></tr>
              </thead>
              <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['course_title']); ?></td>
                  <td><?php echo htmlspecialchars($r['instructor_name']); ?></td>
                  <td><?php echo intval($r['payments_count']); ?></td>
                  <td>$<?php echo number_format($r['total_earned'],2); ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <div style="margin-top:16px;">
        <a href="/dashboard.php" class="btn secondary">Back to Dashboard</a>
      </div>
    </div>
  </main>
</body>
</html>
