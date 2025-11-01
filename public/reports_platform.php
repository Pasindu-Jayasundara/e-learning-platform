<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_login();
require_role(['admin']);

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';

// helper to output CSV
function output_csv($filename, $headers, $rows) {
  // Clean any prior output to avoid corrupting the download
  while (ob_get_level()) { ob_end_clean(); }
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=' . $filename);
  header('Pragma: public');
  header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
  // Add UTF-8 BOM for Excel compatibility
  echo "\xEF\xBB\xBF";
  $out = fopen('php://output', 'w');
  // Provide delimiter, enclosure, and escape to avoid deprecation warnings
  fputcsv($out, $headers, ',', '"', '\\');
  foreach ($rows as $r) {
    // Ensure column order matches headers and handle associative rows
    $line = [];
    foreach ($headers as $h) { $line[] = isset($r[$h]) ? (string)$r[$h] : ''; }
    fputcsv($out, $line, ',', '"', '\\');
  }
  fclose($out);
  exit;
}

// generate simple HTML table for PDF fallback
function rows_to_html($title, $headers, $rows) {
    $html = '<h1>' . htmlspecialchars($title) . '</h1>';
    $html .= '<table border="1" cellpadding="4" cellspacing="0">';
    $html .= '<thead><tr>';
    foreach ($headers as $h) $html .= '<th>' . htmlspecialchars($h) . '</th>';
    $html .= '</tr></thead><tbody>';
  foreach ($rows as $r) {
    $html .= '<tr>';
    foreach ($headers as $h) { $c = $r[$h] ?? ''; $html .= '<td>' . htmlspecialchars((string)$c) . '</td>'; }
    $html .= '</tr>';
  }
    $html .= '</tbody></table>';
    return $html;
}

if ($type) {
  if ($type === 'users') {
    $stmt = $pdo->query('SELECT id, name, email, role, created_at FROM users ORDER BY id');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['id','name','email','role','created_at'];
        if ($format === 'csv') output_csv('users.csv', $headers, $rows);
    $html = rows_to_html('Users', $headers, $rows);
    } elseif ($type === 'courses') {
    $stmt = $pdo->query('SELECT id, title, instructor_id, is_published AS published, created_at FROM courses ORDER BY id');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['id','title','instructor_id','published','created_at'];
        if ($format === 'csv') output_csv('courses.csv', $headers, $rows);
        $html = rows_to_html('Courses', $headers, $rows);
    } elseif ($type === 'enrollments') {
    $stmt = $pdo->query('SELECT e.id, e.user_id, u.name as user_name, e.course_id, c.title as course_title, e.created_at FROM enrollments e LEFT JOIN users u ON e.user_id=u.id LEFT JOIN courses c ON e.course_id=c.id ORDER BY e.id');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['id','user_id','user_name','course_id','course_title','created_at'];
        if ($format === 'csv') output_csv('enrollments.csv', $headers, $rows);
        $html = rows_to_html('Enrollments', $headers, $rows);
    } elseif ($type === 'payments') {
    $stmt = $pdo->query('SELECT p.id, p.user_id, u.name as user_name, p.course_id, c.title as course_title, p.amount, p.status, p.created_at FROM payments p LEFT JOIN users u ON p.user_id=u.id LEFT JOIN courses c ON p.course_id=c.id ORDER BY p.id');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers = ['id','user_id','user_name','course_id','course_title','amount','status','created_at'];
        if ($format === 'csv') output_csv('payments.csv', $headers, $rows);
    $html = rows_to_html('Payments', $headers, $rows);
    } else {
        set_flash('error', 'Unknown report type.'); header('Location: /reports_platform.php'); exit;
    }

    // If PDF requested, try DOMPDF, otherwise stream HTML
  if ($format === 'pdf') {
        $autoloader = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoloader)) {
            require_once $autoloader;
            if (class_exists('Dompdf\Dompdf')) {
        // Clean any prior output and suppress deprecation noise during render
        while (ob_get_level()) { ob_end_clean(); }
        $prevDisplay = ini_get('display_errors');
        $prevReporting = error_reporting();
        ini_set('display_errors', '0');
        error_reporting($prevReporting & ~E_DEPRECATED);
        // Capture any accidental output from the library
        ob_start();
        try {
                    $dompdf = new \Dompdf\Dompdf(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
          $dompdf->loadHtml('<meta charset="utf-8">' . $html);
          $dompdf->setPaper('A4', 'landscape');
          $dompdf->render();
          $pdf = $dompdf->output();
        } finally {
          // Discard any buffered library output and restore settings
          ob_end_clean();
          ini_set('display_errors', $prevDisplay);
          error_reporting($prevReporting);
        }
        // Send the PDF bytes
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="report.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf; exit;
            }
        }
        // fallback: return HTML as download
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="report.html"');
        echo $html; exit;
    }
}

$csrf = generate_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Platform Reports</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    .actions-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:12px}
    .action-card{border:1px solid var(--border-color);border-radius:var(--radius-md);padding:16px;background:var(--bg-primary);box-shadow:var(--shadow-sm)}
    .action-card h3{margin:0 0 8px 0;color:var(--text-primary);font-size:16px}
    .action-card p{margin:0 0 12px 0;color:var(--text-secondary);font-size:13px}
    .inline-controls{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
    .inline-controls label{margin:0}
    .inline-controls select{min-width:160px}
  </style>
  </head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <main>
    <div class="container">
      <div class="page-header">
        <h1>📊 Platform Reports</h1>
        <p>Export platform-wide data as CSV for Excel or PDF for sharing.</p>
      </div>

      <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h2>Export a Report</h2></div>
        <div class="card-body">
          <form class="inline" method="get" action="/reports_platform.php">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
            <div class="inline-controls">
              <label>Report
                <select name="type">
                  <option value="users">Users</option>
                  <option value="courses">Courses</option>
                  <option value="enrollments">Enrollments</option>
                  <option value="payments">Payments</option>
                </select>
              </label>
              <label>Format
                <select name="format">
                  <option value="csv">CSV</option>
                  <option value="pdf">PDF</option>
                </select>
              </label>
              <button type="submit" class="btn primary">Export</button>
              <a href="/dashboard.php" class="btn secondary">Back</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2>Quick Exports</h2></div>
        <div class="card-body">
          <div class="actions-grid">
            <div class="action-card">
              <h3>Users</h3>
              <p>All users with role and join date.</p>
              <div class="inline-controls">
                <a class="btn primary btn-sm" href="/reports_platform.php?type=users&format=csv">CSV</a>
                <a class="btn secondary btn-sm" href="/reports_platform.php?type=users&format=pdf">PDF</a>
              </div>
            </div>
            <div class="action-card">
              <h3>Courses</h3>
              <p>Courses with instructor and publish status.</p>
              <div class="inline-controls">
                <a class="btn primary btn-sm" href="/reports_platform.php?type=courses&format=csv">CSV</a>
                <a class="btn secondary btn-sm" href="/reports_platform.php?type=courses&format=pdf">PDF</a>
              </div>
            </div>
            <div class="action-card">
              <h3>Enrollments</h3>
              <p>Enrollments with learner and course names.</p>
              <div class="inline-controls">
                <a class="btn primary btn-sm" href="/reports_platform.php?type=enrollments&format=csv">CSV</a>
                <a class="btn secondary btn-sm" href="/reports_platform.php?type=enrollments&format=pdf">PDF</a>
              </div>
            </div>
            <div class="action-card">
              <h3>Payments</h3>
              <p>Payments with amounts and statuses.</p>
              <div class="inline-controls">
                <a class="btn primary btn-sm" href="/reports_platform.php?type=payments&format=csv">CSV</a>
                <a class="btn secondary btn-sm" href="/reports_platform.php?type=payments&format=pdf">PDF</a>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>
</body>
</html>
