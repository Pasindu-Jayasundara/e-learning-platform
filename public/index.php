<?php
require_once __DIR__ . '/../includes/auth.php';
$user = current_user();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Learning Platform - Learn Anything, Anytime</title>
    <meta name="description" content="Transform your future with expert-led online courses. Learn at your own pace and earn professional certificates.">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
    
    <main>
        <div class="container">
            <!-- Hero Section -->
            <div class="hero fade-in">
                <div class="inner">
                    <h1>Transform Your Future with Online Learning</h1>
                    <p>Access world-class courses, learn at your own pace, and unlock your potential. Manage courses, track progress, issue certificates and accept payments — all in one comprehensive platform.</p>
                    <div class="cta">
                        <?php if ($user): ?>
                            <a href="/dashboard.php" class="btn primary">🚀 Go to Dashboard</a>
                            <a href="/manage_courses.php" class="btn light">Browse Courses</a>
                        <?php else: ?>
                            <a href="/register.php" class="btn primary">🚀 Get Started Free</a>
                            <a href="/login.php" class="btn light">Sign In</a>
                        <?php endif; ?>
                    </div>
                    <?php if ($user): ?>
                        <p style="margin-top: 20px; opacity: 0.9; font-size: 18px;">
                            Welcome back, <strong><?php echo htmlspecialchars($user['name']); ?></strong>! 👋
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats Section -->
            <div class="stats-grid fade-in">
                <div class="stat-card">
                    <div class="stat-card-label">Active Courses</div>
                    <div class="stat-card-value">100+</div>
                </div>
                <div class="stat-card" style="border-left-color: var(--secondary-color);">
                    <div class="stat-card-label">Expert Instructors</div>
                    <div class="stat-card-value">50+</div>
                </div>
                <div class="stat-card" style="border-left-color: var(--accent-color);">
                    <div class="stat-card-label">Students Enrolled</div>
                    <div class="stat-card-value">10K+</div>
                </div>
                <div class="stat-card" style="border-left-color: #8b5cf6;">
                    <div class="stat-card-label">Completion Rate</div>
                    <div class="stat-card-value">95%</div>
                </div>
            </div>

            <!-- Features Section -->
            <div style="text-align: center; margin: 64px 0 40px;">
                <h2 style="font-size: 36px; margin-bottom: 12px; text-align:center; margin-left:auto; margin-right:auto;">Why Choose Our Platform?</h2>
                <p style="color: var(--text-secondary); font-size: 18px; text-align:center; margin-left:auto; margin-right:auto;">Everything you need to succeed in your learning journey</p>
            </div>

            <div class="features fade-in">
                <div class="feature">
                    <span class="feature-icon">📚</span>
                    <h3>Expert-Led Courses</h3>
                    <p>Create structured courses with materials, quizzes and comprehensive progress tracking for every student.</p>
                </div>
                <div class="feature">
                    <span class="feature-icon">💳</span>
                    <h3>Secure Payments</h3>
                    <p>Integrated with Stripe for secure payment processing, receipts, and revenue tracking.</p>
                </div>
                <div class="feature">
                    <span class="feature-icon">🎓</span>
                    <h3>Professional Certificates</h3>
                    <p>Design custom templates and issue verified certificates as professional PDFs to your students.</p>
                </div>
                <div class="feature">
                    <span class="feature-icon">💬</span>
                    <h3>Interactive Forums</h3>
                    <p>Engage with built-in messaging and forum moderation tools to keep your community active and safe.</p>
                </div>
                <div class="feature">
                    <span class="feature-icon">📊</span>
                    <h3>Analytics & Reports</h3>
                    <p>Track enrollment, completion rates, revenue, and student performance with detailed reporting.</p>
                </div>
                <div class="feature">
                    <span class="feature-icon">🔐</span>
                    <h3>Secure & Reliable</h3>
                    <p>Built with security best practices, CSRF protection, and role-based access control.</p>
                </div>
            </div>
            
                        <!-- Featured Categories -->
                        <div class="fade-in" style="margin: 24px 0 64px;">
                            <h3 style="text-align:center; margin-bottom:16px;">Popular Categories</h3>
                            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                                <div class="card" style="padding:16px; display:flex; gap:10px; align-items:center;">
                                    <span>💻</span><strong>Programming</strong>
                                </div>
                                <div class="card" style="padding:16px; display:flex; gap:10px; align-items:center;">
                                    <span>🎨</span><strong>Design</strong>
                                </div>
                                <div class="card" style="padding:16px; display:flex; gap:10px; align-items:center;">
                                    <span>📈</span><strong>Business</strong>
                                </div>
                                <div class="card" style="padding:16px; display:flex; gap:10px; align-items:center;">
                                    <span>🧠</span><strong>Personal Growth</strong>
                                </div>
                                <div class="card" style="padding:16px; display:flex; gap:10px; align-items:center;">
                                    <span>🔬</span><strong>Data Science</strong>
                                </div>
                            </div>
                        </div>

            <!-- CTA Section -->
            <div class="card fade-in" style="background: linear-gradient(135deg, var(--primary-color) 0%, #8b5cf6 100%); color: #fff; text-align: center; padding: 64px 32px; margin: 64px 0;">
                <h2 style="font-size: 36px; margin-bottom: 16px; color: #fff;">Ready to Start Your Journey?</h2>
                <p style="font-size: 20px; margin-bottom: 32px; opacity: 0.95;">
                    <?php if ($user): ?>
                        Explore our courses and continue your learning path today.
                    <?php else: ?>
                        Join thousands of learners already improving their skills and advancing their careers.
                    <?php endif; ?>
                </p>
                <?php if ($user): ?>
                    <a href="/dashboard.php" class="btn primary" style="font-size: 18px; padding: 16px 32px; box-shadow: 0 8px 20px rgba(0,0,0,0.2);">
                        Go to Your Dashboard
                    </a>
                <?php else: ?>
                    <a href="/register.php" class="btn primary" style="font-size: 18px; padding: 16px 32px; box-shadow: 0 8px 20px rgba(0,0,0,0.2);">
                        Create Your Free Account
                    </a>
                    <p style="margin-top: 16px; font-size: 14px; opacity: 0.8;">No credit card required • Start learning in minutes</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer style="background: var(--text-primary); color: #fff; padding: 48px 0; margin-top: 64px;">
        <div class="container" style="text-align: center;">
            <p style="opacity: 0.8; margin-bottom: 16px;">© 2025 E-Learning Platform. All rights reserved.</p>
            <div style="display: flex; gap: 24px; justify-content: center; flex-wrap: wrap;">
                <a href="/announcements.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">Announcements</a>
                <?php if ($user && $user['role'] === 'admin'): ?>
                    <a href="/reports_platform.php" style="color: rgba(255,255,255,0.8); text-decoration: none;">Platform Reports</a>
                <?php endif; ?>
                <a href="#" style="color: rgba(255,255,255,0.8); text-decoration: none;">Contact</a>
                <a href="#" style="color: rgba(255,255,255,0.8); text-decoration: none;">Privacy Policy</a>
            </div>
        </div>
    </footer>
</body>
</html>