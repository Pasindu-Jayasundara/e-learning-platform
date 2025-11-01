<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

$course_id = intval($_GET['id'] ?? 0);
if (!$course_id) {
    header('Location: /dashboard.php');
    exit;
}

// fetch course and instructor
$stmt = $pdo->prepare('SELECT c.*, u.name AS instructor_name FROM courses c LEFT JOIN users u ON c.instructor_id = u.id WHERE c.id = ? LIMIT 1');
$stmt->execute([$course_id]);
$course = $stmt->fetch();
if (!$course) {
    header('Location: /dashboard.php');
    exit;
}
// if student, only allow viewing published courses
if ($user['role'] === 'student' && !$course['is_published']) {
  set_flash('error','Course not available');
  header('Location: /dashboard.php');
  exit;
}
// check enrollment
$stmt = $pdo->prepare('SELECT * FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
$stmt->execute([$user['id'],$course_id]);
$enrollment = $stmt->fetch();

// fetch materials for this course
$stmt = $pdo->prepare('SELECT * FROM course_materials WHERE course_id = ? ORDER BY uploaded_at DESC');
$stmt->execute([$course_id]);
$materials = $stmt->fetchAll();

// fetch lessons (videos) for this course
$stmt = $pdo->prepare('SELECT * FROM lessons WHERE course_id = ? ORDER BY sort_order ASC, id ASC');
$stmt->execute([$course_id]);
$lessons = $stmt->fetchAll();

// fetch lesson progress for this user
$lessonStatus = [];
if (!empty($lessons)) {
  $stmt = $pdo->prepare('SELECT lesson_id, status FROM lesson_progress WHERE user_id = ? AND course_id = ?');
  $stmt->execute([$user['id'], $course_id]);
  foreach ($stmt->fetchAll() as $row) {
    $lessonStatus[(int)$row['lesson_id']] = $row['status'];
  }
}

// compute progress from lessons if enrolled
$computedProgress = $enrollment ? intval($enrollment['progress']) : 0;
if ($enrollment && !empty($lessons)) {
  $total = count($lessons);
  $completed = 0;
  foreach ($lessons as $lsn) {
    $sid = (int)$lsn['id'];
    if (($lessonStatus[$sid] ?? 'not_started') === 'completed') { $completed++; }
  }
  $computedProgress = $total > 0 ? (int)floor(($completed * 100) / $total) : 0;
}

// if instructor or admin, fetch enrolled students
$students = [];
if ($user['role'] === 'admin' || ($user['role'] === 'instructor' && $course['instructor_id'] == $user['id'])) {
    $stmt = $pdo->prepare('SELECT e.*, u.name, u.email FROM enrollments e JOIN users u ON e.user_id = u.id WHERE e.course_id = ?');
    $stmt->execute([$course_id]);
    $students = $stmt->fetchAll();
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($course['title']); ?> - E-Learning Platform</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  
  <main>
    <div class="container">
      <!-- Course Header -->
      <div class="card fade-in" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%); color: #fff; margin-bottom: 32px;">
        <div style="padding: 48px 32px;">
          <div style="display: inline-block; background: rgba(255,255,255,0.2); padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; margin-bottom: 16px;">
            <?php echo $course['is_published'] ? '✓ Published' : '⏳ Draft'; ?>
          </div>
          <h1 style="margin: 0 0 16px 0; font-size: 36px;"><?php echo htmlspecialchars($course['title']); ?></h1>
          <div style="display: flex; align-items: center; gap: 24px; flex-wrap: wrap; opacity: 0.95;">
            <span style="display: flex; align-items: center; gap: 8px;">
              👨‍🏫 <strong><?php echo htmlspecialchars($course['instructor_name']); ?></strong>
            </span>
            <span style="display: flex; align-items: center; gap: 8px;">
              💰 <strong>$<?php echo htmlspecialchars($course['price']); ?></strong>
            </span>
          </div>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
        <!-- Main Content -->
        <div>
          <!-- Course Description -->
          <div class="card fade-in" style="margin-bottom: 24px;">
            <div class="card-header">
              <h2>📖 Course Description</h2>
            </div>
            <div class="card-body" style="line-height: 1.8; color: var(--text-primary);">
              <?php echo nl2br(htmlspecialchars($course['description'])); ?>
            </div>
          </div>

          <?php if ($user['role'] === 'student'): ?>
            <?php if ($enrollment): ?>
              <!-- Student Progress -->
              <div class="card fade-in" style="margin-bottom: 24px;">
                <div class="card-header">
                  <h2>📊 Your Progress</h2>
                </div>
                <div class="card-body">
                  <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                    <span style="font-weight: 600; color: var(--text-primary);">Course Completion</span>
                    <span style="font-weight: 700; color: var(--primary-color); font-size: 18px;"><?php echo intval($computedProgress); ?>%</span>
                  </div>
                  <div class="progress-container">
                    <div class="progress-bar" style="width: <?php echo intval($computedProgress); ?>%;"></div>
                  </div>
                  <p style="margin-top: 16px; color: var(--text-secondary); font-size: 14px;">
                    <?php if ($computedProgress >= 100): ?>
                      🎉 Congratulations! You've completed this course!
                    <?php elseif ($computedProgress >= 50): ?>
                      💪 You're doing great! Keep going!
                    <?php else: ?>
                      🚀 Let's get started on your learning journey!
                    <?php endif; ?>
                  </p>
                </div>
              </div>
            <?php endif; ?>
          <?php endif; ?>

          <!-- Lessons Section -->
          <?php 
            $can_watch = ($user['role'] !== 'student') || (bool)$enrollment;
          ?>
          <div class="card fade-in" style="margin-bottom: 24px;">
            <div class="card-header">
              <h2>🎬 Lessons</h2>
            </div>
            <div class="card-body">
              <?php if (empty($lessons)): ?>
                <p class="muted">No lessons have been added for this course yet.</p>
              <?php else: ?>
                <div style="display: grid; grid-template-columns: 1.1fr 2fr; gap: 16px; align-items: start;">
                  <!-- Playlist -->
                  <div style="border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; background: var(--bg-secondary);">
                    <?php foreach ($lessons as $idx => $lsn): 
                      $lsnId = (int)$lsn['id'];
                      $isCompleted = ($lessonStatus[$lsnId] ?? 'not_started') === 'completed';
                    ?>
                      <button 
                        class="lesson-item" 
                        data-lesson-id="<?php echo $lsnId; ?>" 
                        data-video-url="<?php echo htmlspecialchars($lsn['video_url']); ?>"
                        style="display:flex; width:100%; text-align:left; gap: 12px; align-items:center; padding: 12px 14px; background: transparent; border: 0; border-bottom: 1px solid var(--border-color); cursor: pointer;">
                        <span style="width: 28px; height: 28px; display:inline-flex; align-items:center; justify-content:center; border-radius: 50%; background: var(--bg-tertiary); font-weight: 700; font-size: 12px; color: var(--text-secondary);">
                          <?php echo $idx + 1; ?>
                        </span>
                        <div style="flex:1; min-width:0;">
                          <div style="font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo htmlspecialchars($lsn['title']); ?>
                          </div>
                          <?php if (!empty($lsn['duration_seconds'])): ?>
                            <div class="muted" style="font-size: 12px;">~<?php echo intval(ceil($lsn['duration_seconds']/60)); ?> min</div>
                          <?php endif; ?>
                        </div>
                        <span class="lesson-status" style="font-size: 16px; min-width: 20px; text-align:right;">
                          <?php echo $isCompleted ? '✅' : '⏺️'; ?>
                        </span>
                      </button>
                    <?php endforeach; ?>
                  </div>
                  <!-- Player -->
                  <div>
                    <?php if ($can_watch): ?>
                      <div id="player-container" style="background: #000; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-color);">
                        <video id="lesson-player" width="100%" height="420" controls preload="metadata" style="display:block;">
                          <!-- src set by JS -->
                          Your browser does not support the video tag.
                        </video>
                      </div>
                      <p id="current-lesson-title" style="margin-top: 8px; font-weight: 600; color: var(--text-primary);"></p>
                      <form id="lp-csrf" style="display:none;"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>"></form>
                    <?php else: ?>
                      <div style="padding: 24px; border: 1px dashed var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary); text-align:center;">
                        <div style="font-size: 48px;">🔒</div>
                        <p style="margin: 8px 0 0;">Enroll in this course to watch videos.</p>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>


          <!-- Course Materials -->
          <?php if (!empty($materials)): ?>
            <div class="card fade-in" style="margin-bottom: 24px;">
              <div class="card-header">
                <h2>📚 Course Materials</h2>
              </div>
              <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 12px;">
                  <?php foreach ($materials as $m): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                      <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 24px;">📄</span>
                        <span style="font-weight: 600; color: var(--text-primary);">
                          <?php echo htmlspecialchars($m['filename']); ?>
                        </span>
                      </div>
                      <div style="display: flex; gap: 8px;">
                        <?php if ($user['role'] === 'admin' || ($user['role'] === 'instructor' && $course['instructor_id']==$user['id'])): ?>
                          <a href="/download_material.php?id=<?php echo $m['id']; ?>" class="btn-sm btn primary">📥 Download</a>
                          <form method="post" action="/delete_material.php" style="display:inline; margin:0;">
                            <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                            <button type="submit" class="btn-sm btn danger">🗑️ Delete</button>
                          </form>
                        <?php else: ?>
                          <?php if ($enrollment): ?>
                            <a href="/download_material.php?id=<?php echo $m['id']; ?>" class="btn-sm btn primary">📥 Download</a>
                          <?php else: ?>
                            <span class="badge warning">Enroll to Access</span>
                          <?php endif; ?>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <?php if (!empty($students)): ?>
            <!-- Enrolled Students (Instructor/Admin View) -->
            <div class="card fade-in">
              <div class="card-header">
                <h2>👥 Enrolled Students (<?php echo count($students); ?>)</h2>
              </div>
              <div class="card-body">
                <table>
                  <thead>
                    <tr>
                      <th>Student</th>
                      <th>Email</th>
                      <th>Progress</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($students as $s): ?>
                      <tr>
                        <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($s['email']); ?></td>
                        <td>
                          <div style="display: flex; align-items: center; gap: 8px;">
                            <div class="progress-container" style="flex-grow: 1; margin: 0;">
                              <div class="progress-bar" style="width: <?php echo intval($s['progress']); ?>%;"></div>
                            </div>
                            <span style="font-weight: 600; min-width: 40px;"><?php echo intval($s['progress']); ?>%</span>
                          </div>
                        </td>
                        <td>
                          <?php if ($user['role'] === 'admin' || ($user['role'] === 'instructor' && $course['instructor_id'] == $user['id'])): ?>
                            <form method="post" action="/update_progress.php" style="display:flex; gap: 8px; align-items:center; margin:0;">
                              <input type="hidden" name="enrollment_id" value="<?php echo $s['id']; ?>">
                              <input type="number" name="progress" value="<?php echo intval($s['progress']); ?>" min="0" max="100" style="width:70px; padding: 6px;">
                              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                              <button type="submit" class="btn-sm btn primary">Update</button>
                            </form>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div>
          <?php if ($user['role'] === 'student' && !$enrollment): ?>
            <!-- Enrollment Card -->
            <div class="card fade-in" style="position: sticky; top: 100px;">
              <div class="card-header" style="text-align: center;">
                <h3>🎯 Enroll Now</h3>
              </div>
              <div class="card-body" style="text-align: center;">
                <div style="font-size: 36px; font-weight: 700; color: var(--primary-color); margin: 16px 0;">
                  $<?php echo htmlspecialchars($course['price']); ?>
                </div>
                <form method="post" action="/enroll.php" style="padding: 0; box-shadow: none;">
                  <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                  <button type="submit" class="btn primary" style="width: 100%; font-size: 16px; padding: 14px;">
                    🚀 Enroll in Course
                  </button>
                </form>
                <p style="margin-top: 16px; font-size: 13px; color: var(--text-secondary);">
                  Get instant access to all course materials
                </p>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($enrollment): ?>
            <!-- Quick Actions for Enrolled Student -->
            <div class="card fade-in" style="position: sticky; top: 100px;">
              <div class="card-header">
                <h3>⚡ Quick Actions</h3>
              </div>
              <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 12px;">
                  <a href="/course_forum.php?course_id=<?php echo $course['id']; ?>" class="btn secondary" style="text-align: center;">
                    💬 Course Forum
                  </a>
                  <?php if (intval($computedProgress) >= 100): ?>
                    <a href="/certificate.php?course_id=<?php echo $course['id']; ?>" class="btn secondary" style="text-align: center;">
                      🎓 Get Certificate
                    </a>
                  <?php else: ?>
                    <button class="btn secondary" style="text-align: center; opacity: 0.6; cursor: not-allowed;" disabled title="Complete the course to unlock your certificate">
                      🎓 Get Certificate (Complete course to unlock)
                    </button>
                  <?php endif; ?>
                  <a href="/course_messages.php?course_id=<?php echo $course['id']; ?>" class="btn secondary" style="text-align: center;">
                    📫 Messages
                  </a>
                  <a href="/my_progress.php" class="btn secondary" style="text-align: center;">
                    📊 View All Progress
                  </a>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div style="margin-top: 32px; padding-bottom: 48px;">
        <a href="/dashboard.php" class="btn light" style="color: var(--primary-color); border: 2px solid var(--primary-color);">
          ← Back to Dashboard
        </a>
      </div>
    </div>
  </main>
  <?php if (!empty($lessons)): ?>
  <script>
    (function(){
      const canWatch = <?php echo $can_watch ? 'true' : 'false'; ?>;
      const items = Array.from(document.querySelectorAll('.lesson-item'));
      const player = document.getElementById('lesson-player');
      const titleEl = document.getElementById('current-lesson-title');
      const csrfInput = document.querySelector('#lp-csrf input[name="csrf"]');
      let activeId = null;
      let progressMap = {}; // lessonId -> { status, watched_seconds }
  let saveThrottleTimer = null;
      let lastSavedSecond = 0;

      function setActive(btn){
        items.forEach(b => b.style.background = 'transparent');
        if (btn) btn.style.background = 'var(--bg-tertiary)';
      }

      function playLesson(btn){
        if (!canWatch || !player) return;
        const url = btn.getAttribute('data-video-url');
        const id = btn.getAttribute('data-lesson-id');
        const title = btn.querySelector('div > div').textContent.trim();
        activeId = parseInt(id, 10);
        // Reset source and wait for metadata before seeking
        player.pause();
        player.src = url;
        const saved = progressMap[activeId] ? (progressMap[activeId].watched_seconds || 0) : 0;
        const onMeta = () => {
          try { player.currentTime = saved; } catch(e) {}
          lastSavedSecond = saved;
          player.play().catch(()=>{});
          player.removeEventListener('loadedmetadata', onMeta);
        };
        if (player.readyState >= 1) {
          onMeta();
        } else {
          player.addEventListener('loadedmetadata', onMeta);
        }
        if (titleEl) titleEl.textContent = title;
        setActive(btn);
      }

      function savePositionImmediate(){
        if (!canWatch || !player || !activeId) return;
        if (!csrfInput) return;
        const cur = Math.floor(player.currentTime || 0);
        if (cur < 0 || cur === lastSavedSecond) return;
        lastSavedSecond = cur;
        fetch('/api/lesson_progress.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ lesson_id: String(activeId), position_seconds: String(cur), csrf: csrfInput.value })
        }).then(r => r.json()).then(data => {
          // update local map
          progressMap[activeId] = progressMap[activeId] || { status: 'in_progress', watched_seconds: 0 };
          progressMap[activeId].watched_seconds = cur;
          progressMap[activeId].status = 'in_progress';
        }).catch(()=>{});
      }

      function savePositionThrottled(){
        if (saveThrottleTimer) return;
        saveThrottleTimer = setTimeout(() => { saveThrottleTimer = null; savePositionImmediate(); }, 2000);
      }

      // click handlers
      items.forEach(btn => {
        btn.addEventListener('click', () => {
          // save current before switching
          savePositionImmediate();
          playLesson(btn);
        });
      });

      // fetch lesson progress to enable resume
      function initAndMaybeAutoplay(){
        if (!canWatch || !items.length) return;
        fetch('/api/lesson_progress.php?course_id=<?php echo (int)$course_id; ?>')
          .then(r => r.json())
          .then(data => {
            if (data && data.ok && Array.isArray(data.lessons)) {
              data.lessons.forEach(row => {
                progressMap[parseInt(row.lesson_id, 10)] = { status: row.status, watched_seconds: parseInt(row.watched_seconds || 0, 10) };
              });
              // pick the best lesson to resume: most recently in progress or with max watched time
              let bestIndex = 0;
              let bestScore = -1;
              items.forEach((btn, idx) => {
                const id = parseInt(btn.getAttribute('data-lesson-id'), 10);
                const rec = progressMap[id];
                if (!rec) return;
                // completed gets lowest priority
                if (rec.status === 'completed') return;
                const score = rec.watched_seconds || 0;
                if (score > bestScore) { bestScore = score; bestIndex = idx; }
              });
              // fallthrough: if none found, keep 0
              playLesson(items[bestIndex]);
              return;
            }
          })
          .finally(() => {
            // if no data or something failed, default to first
            if (!activeId && items.length) playLesson(items[0]);
          });
      }

      // mark complete on ended
      if (player && canWatch) {
        player.addEventListener('timeupdate', () => {
          if (!activeId) return;
          // throttle saves while watching
          savePositionThrottled();
        });
        player.addEventListener('pause', () => {
          savePositionImmediate();
        });
        player.addEventListener('ended', () => {
          if (!activeId) return;
          const csrf = csrfInput ? csrfInput.value : '';
          fetch('/api/lesson_progress.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ lesson_id: String(activeId), csrf })
          })
          .then(r => r.json())
          .then(data => {
            if (data && data.ok) {
              // update progress bar percents if present
              const bars = document.querySelectorAll('.progress-bar');
              bars.forEach(b => { b.style.width = (data.progress_percent || 0) + '%'; });
              const percEls = document.querySelectorAll('span');
              // update percentage text in the Your Progress card (best-effort - we only update the first matching)
              const progText = document.querySelector('.card .card-header h2') ? document.querySelector('.card .card-header h2').textContent : '';
              // set checkmark on the active lesson
              const statusEl = document.querySelector('.lesson-item[data-lesson-id="' + activeId + '"] .lesson-status');
              if (statusEl) statusEl.textContent = '✅';
              // also update the numeric percent next to bar
              const pctLabel = document.querySelector('.card-body span[style*="font-weight: 700"][style*="font-size: 18px"]');
              if (pctLabel) pctLabel.textContent = (data.progress_percent || 0) + '%';
              // reset saved position on completion
              if (progressMap[activeId]) { progressMap[activeId].watched_seconds = 0; progressMap[activeId].status = 'completed'; }
            }
          }).catch(() => {});
        });

        window.addEventListener('beforeunload', () => { savePositionImmediate(); });
        document.addEventListener('visibilitychange', () => { if (document.hidden) savePositionImmediate(); });
      }

      // init
      if (items.length && canWatch) {
        initAndMaybeAutoplay();
      }
    })();
  </script>
  <?php endif; ?>
</body>
</html>
