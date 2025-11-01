<?php
/**
 * Add Sample Data Script
 * Creates sample courses, instructors, and enrollments for testing
 */

require_once __DIR__ . '/../includes/db.php';

echo "=== Adding Sample Data to E-Learning Platform ===\n\n";

try {
    // Check if we already have courses
    $count = $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
    if ($count > 0) {
        echo "⚠️  Warning: Database already has $count courses.\n";
        echo "Do you want to add more sample data? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (trim(strtolower($line)) !== 'y') {
            echo "Cancelled.\n";
            exit(0);
        }
    }

    // Create sample instructors if they don't exist
    echo "📝 Creating sample instructors...\n";
    
    $instructors = [
        ['name' => 'Dr. Sarah Johnson', 'email' => 'sarah.johnson@example.com', 'password' => password_hash('instructor123', PASSWORD_DEFAULT)],
        ['name' => 'Prof. Michael Chen', 'email' => 'michael.chen@example.com', 'password' => password_hash('instructor123', PASSWORD_DEFAULT)],
        ['name' => 'Dr. Emily Rodriguez', 'email' => 'emily.rodriguez@example.com', 'password' => password_hash('instructor123', PASSWORD_DEFAULT)],
    ];

    $instructorIds = [];
    foreach ($instructors as $inst) {
        // Check if instructor exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$inst['email']]);
        $existing = $stmt->fetchColumn();
        
        if ($existing) {
            $instructorIds[] = $existing;
            echo "  ✓ Instructor exists: {$inst['name']}\n";
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$inst['name'], $inst['email'], $inst['password'], 'instructor']);
            $instructorIds[] = $pdo->lastInsertId();
            echo "  ✓ Created instructor: {$inst['name']}\n";
        }
    }

    // Create sample courses
    echo "\n📚 Creating sample courses...\n";
    
    $courses = [
        [
            'title' => 'Introduction to Web Development',
            'description' => 'Learn the fundamentals of web development including HTML, CSS, and JavaScript. Build your first responsive website from scratch.',
            'price' => 49.99,
            'is_published' => 1,
        ],
        [
            'title' => 'Advanced PHP Programming',
            'description' => 'Master PHP development with object-oriented programming, database integration, and modern PHP frameworks.',
            'price' => 79.99,
            'is_published' => 1,
        ],
        [
            'title' => 'Database Design and SQL',
            'description' => 'Learn how to design efficient databases, write complex SQL queries, and optimize database performance.',
            'price' => 59.99,
            'is_published' => 1,
        ],
        [
            'title' => 'React.js for Beginners',
            'description' => 'Build modern web applications with React. Learn components, hooks, state management, and routing.',
            'price' => 69.99,
            'is_published' => 1,
        ],
        [
            'title' => 'Python Data Science Fundamentals',
            'description' => 'Introduction to data science using Python. Learn pandas, numpy, matplotlib, and basic machine learning.',
            'price' => 89.99,
            'is_published' => 1,
        ],
        [
            'title' => 'UI/UX Design Principles',
            'description' => 'Master the art of user interface and experience design. Learn design thinking, prototyping, and user research.',
            'price' => 0.00,
            'is_published' => 1,
        ],
        [
            'title' => 'Mobile App Development',
            'description' => 'Build cross-platform mobile applications using modern frameworks. Deploy to iOS and Android.',
            'price' => 99.99,
            'is_published' => 1,
        ],
        [
            'title' => 'Cybersecurity Essentials',
            'description' => 'Learn the fundamentals of cybersecurity, including network security, encryption, and ethical hacking basics.',
            'price' => 0.00,
            'is_published' => 1,
        ],
    ];

    $courseIds = [];
    foreach ($courses as $index => $course) {
        $instructorId = $instructorIds[$index % count($instructorIds)];
        
        $stmt = $pdo->prepare('INSERT INTO courses (title, description, price, is_published, instructor_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $course['title'],
            $course['description'],
            $course['price'],
            $course['is_published'],
            $instructorId
        ]);
        $courseIds[] = $pdo->lastInsertId();
        echo "  ✓ Created: {$course['title']}\n";
    }

    // Get the first student user to enroll in courses
    echo "\n👥 Looking for student users to enroll...\n";
    $stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'student' LIMIT 1");
    $student = $stmt->fetch();

    if ($student) {
        echo "  ✓ Found student: {$student['name']}\n";
        echo "\n📝 Creating sample enrollments with progress...\n";
        
        // Enroll student in first 4 courses with varying progress
        $enrollments = [
            ['course_idx' => 0, 'progress' => 75],
            ['course_idx' => 1, 'progress' => 45],
            ['course_idx' => 2, 'progress' => 100],
            ['course_idx' => 3, 'progress' => 20],
        ];

        foreach ($enrollments as $enrollment) {
            $courseId = $courseIds[$enrollment['course_idx']];
            $courseName = $courses[$enrollment['course_idx']]['title'];
            
            // Check if already enrolled
            $stmt = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?');
            $stmt->execute([$student['id'], $courseId]);
            if ($stmt->fetchColumn()) {
                echo "  ⚠️  Already enrolled in: $courseName\n";
                continue;
            }
            
            $stmt = $pdo->prepare('INSERT INTO enrollments (user_id, course_id, progress, enrolled_at) VALUES (?, ?, ?, NOW())');
            $stmt->execute([$student['id'], $courseId, $enrollment['progress']]);
            echo "  ✓ Enrolled in: $courseName (Progress: {$enrollment['progress']}%)\n";
        }
    } else {
        echo "  ⚠️  No student users found. Please register a student account first.\n";
    }

    echo "\n✅ Sample data added successfully!\n\n";
    echo "📊 Summary:\n";
    echo "  - Instructors: " . count($instructorIds) . "\n";
    echo "  - Courses: " . count($courseIds) . "\n";
    if ($student) {
        echo "  - Enrollments: " . count($enrollments) . "\n";
    }
    echo "\n🌐 Visit http://localhost:8000 to see your courses!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
