<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['instructor']);
$user = current_user();

// Feature deprecated: certificates are automatic at 100% completion. Redirect away.
set_flash('error','Manual certificate requests are disabled. Certificates are automatic when learners complete all lessons.');
header('Location: /dashboard.php');
exit;

// fetch instructor's courses
$coursesStmt = $db->prepare('SELECT id, title FROM courses WHERE instructor_id = ? ORDER BY title');
$coursesStmt->execute([$user['id']]);
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

// templates
$tplStmt = $db->query('SELECT id, name FROM certificate_templates ORDER BY id DESC');
$templates = $tplStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf'] ?? '')) { set_flash('error','Invalid CSRF token'); header('Location:/request_certificate.php'); exit; }
    $course_id = (int)($_POST['course_id'] ?? 0);
    $student_email = trim($_POST['student_email'] ?? '');
    $template_id = !empty($_POST['template_id']) ? (int)$_POST['template_id'] : null;

    if ($course_id <= 0 || $student_email === '') { set_flash('error','Please select a course and student.'); header('Location:/request_certificate.php'); exit; }

    // verify instructor owns course
    $cq = $db->prepare('SELECT id FROM courses WHERE id = ? AND instructor_id = ? LIMIT 1');
    $cq->execute([$course_id, $user['id']]);
    if (!$cq->fetch()) { set_flash('error','Invalid course selection.'); header('Location:/request_certificate.php'); exit; }

    // find student
    $s = $db->prepare('SELECT id, email FROM users WHERE email = ? LIMIT 1');
    $s->execute([$student_email]);
    $stu = $s->fetch(PDO::FETCH_ASSOC);
    if (!$stu) { set_flash('error','Student not found.'); header('Location:/request_certificate.php'); exit; }

    // verify enrollment exists
    $en = $db->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
    $en->execute([$stu['id'], $course_id]);
    if (!$en->fetch()) { set_flash('error','Student is not enrolled in the selected course.'); header('Location:/request_certificate.php'); exit; }

    // create certificate request
    $ins = $db->prepare('INSERT INTO certificates (template_id, user_id, course_id, status, metadata) VALUES (?, ?, ?, ?, ?)');
    $meta = json_encode(['requested_by' => $user['id']]);
    $ok = $ins->execute([$template_id, $stu['id'], $course_id, 'requested', $meta]);
    if ($ok) {
        set_flash('success','Certificate request created.');
        // notify admins/instructors? best-effort: notify course instructor (self) and admins
        header('Location:/instructor_cert_requests.php'); exit;
    }
    set_flash('error','Failed to create request.'); header('Location:/request_certificate.php'); exit;
}

$csrf = generate_csrf_token();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Request Certificate</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>.wide{width:100%;max-width:800px}</style>
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <h1>Request Certificate</h1>
  <?php if ($msg = get_flash('error')): ?><div style="color:red"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
  <?php if ($msg = get_flash('success')): ?><div style="color:green"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

  <form method="post" class="wide" action="/request_certificate.php">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <label>Course<br>
      <select name="course_id" required>
        <option value="">-- select --</option>
        <?php foreach($courses as $c): ?>
          <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option>
        <?php endforeach; ?>
      </select>
    </label><br>

    <label>Student (email)<br>
      <input id="student_input" name="student_email" type="email" required placeholder="start typing name or email...">
      <div id="student_suggestions" style="border:1px solid #ccc;display:none;position:relative;background:#fff;max-height:200px;overflow:auto"></div>
    </label><br>

    <label>Template (optional)<br>
      <select name="template_id">
        <option value="">-- default --</option>
        <?php foreach($templates as $t): ?>
          <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </label><br>

    <button type="submit">Create Request</button>
  </form>

  <p><a href="/dashboard.php">Back</a></p>

  <script>
  (function(){
    const input = document.getElementById('student_input');
    const box = document.getElementById('student_suggestions');
    let timer = null;
    input.addEventListener('input', function(){
      const q = input.value.trim();
      if (timer) clearTimeout(timer);
      if (q.length < 2) { box.style.display='none'; return; }
      timer = setTimeout(()=>{
        fetch('/user_search.php?q=' + encodeURIComponent(q))
          .then(r=>r.json())
          .then(list=>{
            box.innerHTML='';
            if (!list.length) { box.style.display='none'; return; }
            list.forEach(u=>{
              const el = document.createElement('div');
              el.style.padding='6px'; el.style.cursor='pointer';
              el.textContent = (u.name ? u.name + ' <' + u.email + '>' : u.email);
              el.addEventListener('click', ()=>{ input.value = u.email; box.style.display='none'; });
              box.appendChild(el);
            });
            box.style.display = 'block';
          })
          .catch(()=>{ box.style.display='none'; });
      }, 250);
    });
    document.addEventListener('click', function(e){ if (!box.contains(e.target) && e.target !== input) box.style.display='none'; });
  })();
  </script>
</body>
</html>
