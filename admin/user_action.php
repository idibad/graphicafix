<?php
// Prevent any output before JSON
ob_start();

require_once __DIR__ . '/../core/config.php';

// Clear any previous output and set JSON header
ob_clean();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
$user_id = intval($_SESSION['user_id']);
$user_res = $conn->query("SELECT role FROM users WHERE user_id = $user_id");
$user_data = $user_res->fetch_assoc();
if (!$user_data || strtolower($user_data['role']) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'fetch':
            fetchUser($conn);
            break;
        case 'save':
            saveUser($conn);
            break;
        case 'delete':
            deleteUser($conn);
            break;
        case 'toggle_status':
            toggleUserStatus($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function toggleUserStatus($conn) {
    // Auto-patch status column check
    $cols = array_column($conn->query("SHOW COLUMNS FROM users")->fetch_all(MYSQLI_ASSOC), 'Field');
    if (!in_array('status', $cols)) {
        $conn->query("ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
    }

    $user_id = intval($_GET['id'] ?? ($_POST['user_id'] ?? 0));
    
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        return;
    }
    
    // Prevent deactivating logged-in account
    if ($user_id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account']);
        return;
    }
    
    // Check current status
    $stmt = $conn->prepare("SELECT status FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        $stmt->close();
        return;
    }
    $row = $res->fetch_assoc();
    $stmt->close();
    
    $current_status = strtolower(trim($row['status'] ?? 'active'));
    if ($current_status === 'deactivate') {
        $current_status = 'deactivated';
    }

    $req_target = isset($_GET['target_status']) ? strtolower(trim($_GET['target_status'])) : '';
    if ($req_target === 'deactivate' || $req_target === 'deactivated') {
        $target_status = 'deactivated';
    } else if ($req_target === 'activate' || $req_target === 'active') {
        $target_status = 'active';
    } else {
        $target_status = ($current_status === 'deactivated') ? 'active' : 'deactivated';
    }
    
    $updateStmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $updateStmt->bind_param("si", $target_status, $user_id);
    
    if ($updateStmt->execute()) {
        $msg = ($target_status === 'deactivated') ? 'User account deactivated successfully' : 'User account activated successfully';
        echo json_encode(['success' => true, 'status' => $target_status, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user status: ' . $updateStmt->error]);
    }
    $updateStmt->close();
}

function fetchUser($conn) {
    $user_id = intval($_GET['id'] ?? 0);
    
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        return;
    }
    
    // Auto-patch status column check
    $cols = array_column($conn->query("SHOW COLUMNS FROM users")->fetch_all(MYSQLI_ASSOC), 'Field');
    $has_status = in_array('status', $cols);
    
    $query = $has_status ? 
        "SELECT user_id, name, email, username, role, status, client_id FROM users WHERE user_id = ?" : 
        "SELECT user_id, name, email, username, role, client_id FROM users WHERE user_id = ?";
        
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (!isset($user['status'])) {
            $user['status'] = 'active';
        }
        // Add success flag to the response
        $user['success'] = true;
        echo json_encode($user);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    
    $stmt->close();
}

function saveUser($conn) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role = trim($_POST['role'] ?? 'User');
    $status = trim($_POST['status'] ?? 'active');
    $password = trim($_POST['password'] ?? '');
    $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
    
    // Validate required fields
    if (empty($name) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Name and email are required']);
        return;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }

    // Ensure status column exists in table
    $cols = array_column($conn->query("SHOW COLUMNS FROM users")->fetch_all(MYSQLI_ASSOC), 'Field');
    if (!in_array('status', $cols)) {
        $conn->query("ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
    }
    
    if ($user_id > 0) {
        // UPDATE existing user
        
        // Prevent self-deactivation via form
        if ($user_id == $_SESSION['user_id'] && strtolower($status) === 'deactivated') {
            echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account']);
            return;
        }

        // Check if email is already taken by another user
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email is already taken by another user']);
            $stmt->close();
            return;
        }
        $stmt->close();
        
        // Update with or without password
        if (!empty($password)) {
            // Validate password length
            if (strlen($password) < 8) {
                echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
                return;
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ?, password = ?, client_id = ? WHERE user_id = ?");
            $stmt->bind_param("sssssii", $name, $email, $role, $status, $hashed_password, $client_id, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ?, client_id = ? WHERE user_id = ?");
            $stmt->bind_param("ssssii", $name, $email, $role, $status, $client_id, $user_id);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user: ' . $stmt->error]);
        }
        
        $stmt->close();
        
    } else {
        // INSERT new user
        
        // Validate username for new user
        if (empty($username)) {
            echo json_encode(['success' => false, 'message' => 'Username is required']);
            return;
        }
        
        // Validate password for new user
        if (empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Password is required for new users']);
            return;
        }
        
        if (strlen($password) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
            return;
        }
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email is already registered']);
            $stmt->close();
            return;
        }
        $stmt->close();
        
        // Check if username already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username is already taken']);
            $stmt->close();
            return;
        }
        $stmt->close();
        
        // Hash password and insert
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, username, role, status, password, client_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssssi", $name, $email, $username, $role, $status, $hashed_password, $client_id);
        
        if ($stmt->execute()) {
            // Send welcome email
            require_once __DIR__ . '/../core/task_email_helper.php';
            $heading = "Welcome to Graphicafix!";
            $subject = $heading;
            
            $loginUrl = 'https://graphicafix.com/login.php'; // Adjust based on portal
            if ($role === 'student' || $role === 'teacher') {
                $loginUrl = 'https://graphicafix.com/portal/login.php';
            } else {
                $loginUrl = 'https://graphicafix.com/admin/login.php';
            }
            
            $bodyHtml = "
<p style='margin: 0 0 20px 0; font-size: 16px; font-weight: 600; color: #0f172a;'>Hello " . htmlspecialchars($name) . ",</p>
<p style='margin: 0 0 24px 0; color: #475569;'>Your account has been successfully created on <strong style='color:#0f172a;'>Graphicafix</strong>.</p>

<div style='background: #f8fafc; border-radius: 12px; padding: 24px; border: 1px solid #e2e8f0; margin-bottom: 24px;'>
    <table style='width: 100%; border-collapse: collapse;'>
        <tr>
            <td style='padding: 8px 0; color: #64748b; font-weight: 600; font-size: 13.5px; width: 130px; border-bottom: 1px solid #f1f5f9;'>Name:</td>
            <td style='padding: 8px 0; color: #0f172a; font-weight: 700; font-size: 14.5px; border-bottom: 1px solid #f1f5f9;'>" . htmlspecialchars($name) . "</td>
        </tr>
        <tr>
            <td style='padding: 8px 0; color: #64748b; font-weight: 600; font-size: 13.5px; border-bottom: 1px solid #f1f5f9;'>Username:</td>
            <td style='padding: 8px 0; color: #0f172a; font-weight: 700; font-size: 14.5px; border-bottom: 1px solid #f1f5f9;'>" . htmlspecialchars($username) . "</td>
        </tr>
        <tr>
            <td style='padding: 8px 0; color: #64748b; font-weight: 600; font-size: 13.5px; border-bottom: 1px solid #f1f5f9;'>Email:</td>
            <td style='padding: 8px 0; color: #0f172a; font-weight: 700; font-size: 14px; border-bottom: 1px solid #f1f5f9;'>" . htmlspecialchars($email) . "</td>
        </tr>
        <tr>
            <td style='padding: 8px 0; color: #64748b; font-weight: 600; font-size: 13.5px;'>Role:</td>
            <td style='padding: 8px 0; color: #0f172a; font-weight: 700; font-size: 14px;'>" . htmlspecialchars(ucfirst($role)) . "</td>
        </tr>
    </table>
</div>

<div style='margin: 32px 0 20px 0; text-align: center;'>
    <a href='{$loginUrl}' style='background: linear-gradient(135deg, #024442 0%, #035e5b 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 700; font-size: 14.5px; display: inline-block; box-shadow: 0 4px 14px rgba(2,68,66,0.25); letter-spacing: 0.3px;'>Login to Your Account &rarr;</a>
</div>

<p style='margin: 20px 0 0 0; color: #475569;'>We recommend changing your password after your first login for security purposes.</p>";
            
            sendTaskEmail($email, $subject, $heading, $bodyHtml, 0);

            echo json_encode(['success' => true, 'message' => 'User created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create user: ' . $stmt->error]);
        }
        
        $stmt->close();
    }
}

function deleteUser($conn) {
    $user_id = intval($_GET['id'] ?? 0);
    
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        return;
    }
    
    // Prevent deleting yourself
    if ($user_id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user: ' . $stmt->error]);
    }
    
    $stmt->close();
}

$conn->close();
?>