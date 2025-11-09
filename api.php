<?php
// --- API.PHP ---
// Lab Management System Backend
// (Telegram Alert System ke saath Updated)

// 1. Session Start (Login ke liye zaroori)
session_start();

// --- (NEW) TELEGRAM CONFIG ---
// Aapke Bot ka Token aur Chat ID yahaan daal diye gaye hain
$telegram_bot_token = '8278001922:AAEpB-1b33KjGGUoeKf1sEKbAQdtd8ZoETo'; 
$telegram_chat_id = '5651800632'; 
// ------------------------------------

// 2. Database Connection
$db_host = 'localhost';
$db_user = 'root'; 
$db_pass = '';     
$db_name = 'lab_db'; 

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => "Database connection failed: " . $e->getMessage()]));
}

// 3. Helper Functions
function sendResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        // 'role' key ko response mein shaamil karna
        if (isset($data['role'])) {
            $response['role'] = $data['role'];
        }
        if ($data !== null && !isset($data['role'])) {
            $response['data'] = $data;
        } else if (is_array($data) && count($data) > 1) {
             $response['data'] = $data;
        }
    }
    echo json_encode($response);
    exit;
}

// Security Check: Kya user Admin hai?
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Security Check: Kya user Logged in hai?
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// --- (NEW) Telegram Helper Function ---
function sendTelegramMessage($token, $chatId, $message) {
    // Markdown formatting ke liye message ko encode karna
    $encodedMessage = urlencode($message);
    $url = "https://api.telegram.org/bot{$token}/sendMessage?chat_id={$chatId}&text={$encodedMessage}&parse_mode=Markdown";
    
    // Server ko message bhejna
    // @ error ko suppress karta hai agar request fail ho
    $result = @file_get_contents($url);
    
    return $result !== false;
}
// ------------------------------------

// 4. JSON Input ko padhna (Fetch API 'POST' ke liye)
$input = json_decode(file_get_contents('php://input'), true);

// 5. Main Action Router
$action = $_GET['action'] ?? null;

switch ($action) {
    // --- USER AUTH ACTIONS ---
    case 'register':
        handleRegister($pdo, $input);
        break;
    case 'login':
        handleLogin($pdo, $input);
        break;

    // --- COMPONENT ACTIONS ---
    case 'getComponents':
        if (!isLoggedIn()) sendResponse(false, 'Not logged in');
        getComponents($pdo);
        break;
    case 'addComponent':
        if (!isAdmin()) sendResponse(false, 'Access Denied: Admin only');
        addComponent($pdo, $input);
        break;
    case 'updateStock':
        if (!isAdmin()) sendResponse(false, 'Access Denied: Admin only');
        updateStock($pdo, $input);
        break;

    // --- ISSUED RECORDS ACTIONS ---
    case 'getIssuedRecords':
        if (!isLoggedIn()) sendResponse(false, 'Not logged in');
        getIssuedRecords($pdo);
        break;
    case 'issue':
        if (!isAdmin()) sendResponse(false, 'Access Denied: Admin only');
        issueComponent($pdo, $input);
        break;
    case 'return':
        if (!isAdmin()) sendResponse(false, 'Access Denied: Admin only');
        returnComponent($pdo, $input);
        break;

    // --- ALERT ACTION ---
    case 'sendAlerts':
        if (!isAdmin()) sendResponse(false, 'Access Denied: Admin only');
        // (MODIFIED) Telegram variables ko pass karna
        sendOverdueAlerts($pdo, $telegram_bot_token, $telegram_chat_id);
        break;

    default:
        sendResponse(false, 'Invalid action specified.');
}

// --- Function Definitions ---

// --- AUTH FUNCTIONS ---
function handleRegister($pdo, $data) {
    $username = $data['username'] ?? null;
    $password = $data['password'] ?? null;
    $role = $data['role'] ?? null;

    if (empty($username) || empty($password) || empty($role)) {
        sendResponse(false, 'All fields are required.');
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password_hash, $role]);
        sendResponse(true, 'User registered successfully. Please login.');
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { 
            sendResponse(false, 'Username already exists.');
        }
        sendResponse(false, 'Registration failed: ' . $e->getMessage());
    }
}

function handleLogin($pdo, $data) {
    $username = $data['username'] ?? null;
    $password = $data['password'] ?? null;

    if (empty($username) || empty($password)) {
        sendResponse(false, 'Username and password are required.');
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        sendResponse(true, 'Login successful.', ['role' => $user['role']]);
    } else {
        sendResponse(false, 'Invalid username or password.');
    }
}

// --- COMPONENT FUNCTIONS ---
function getComponents($pdo) {
    $stmt = $pdo->query("SELECT * FROM components ORDER BY component_name");
    $components = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(true, 'Components fetched.', $components);
}

function addComponent($pdo, $data) {
    $name = $data['name'] ?? null;
    $totalStock = $data['totalStock'] ?? null;

    if (empty($name) || $totalStock === null || $totalStock < 0) {
        sendResponse(false, 'Component name and valid stock are required.');
    }

    $stmt = $pdo->prepare("INSERT INTO components (component_name, total_stock) VALUES (?, ?)");
    $stmt->execute([$name, $totalStock]);
    sendResponse(true, 'Component added successfully.');
}

function updateStock($pdo, $data) {
    $id = $data['id'] ?? null;
    $newStock = $data['newStock'] ?? null;

    if (empty($id) || $newStock === null || $newStock < 0) {
        sendResponse(false, 'Component ID and new stock are required.');
    }

    $stmt = $pdo->prepare("UPDATE components SET total_stock = ? WHERE component_id = ?");
    $stmt->execute([$newStock, $id]);
    sendResponse(true, 'Stock updated successfully.');
}

// --- ISSUED RECORDS FUNCTIONS ---
function getIssuedRecords($pdo) {
    $stmt = $pdo->query("SELECT * FROM issued_records ORDER BY issue_date DESC");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(true, 'Issued records fetched.', $records);
}

function issueComponent($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO issued_records 
        (component_id, student_name, student_email, student_phone, issue_date, return_date, issued_by_assistant) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    try {
         $stmt->execute([
            $data['component_id'],
            $data['student_name'],
            $data['student_email'],
            $data['student_phone'],
            $data['issue_date'],
            $data['return_date'],
            $data['issued_by_assistant']
        ]);
        sendResponse(true, 'Component issued successfully.');
    } catch (PDOException $e) {
        sendResponse(false, 'Could not issue component: ' . $e->getMessage());
    }
}

function returnComponent($pdo, $data) {
    $record_id = $data['record_id'] ?? null;
    if (empty($record_id)) {
        sendResponse(false, 'Record ID is required.');
    }

    $today = date('Y-m-d'); 
    $stmt = $pdo->prepare("UPDATE issued_records SET is_returned = 1, actual_return_date = ? WHERE record_id = ?");
    $stmt->execute([$today, $record_id]);

    sendResponse(true, 'Component marked as returned.');
}

// --- (MODIFIED) ALERT FUNCTION ---
function sendOverdueAlerts($pdo, $token, $chatId) {
    $today = date('Y-m-d');
    
    // Overdue students aur component ka naam fetch karna (JOIN ka istemaal)
    $stmt = $pdo->prepare("
        SELECT r.student_name, r.return_date, c.component_name 
        FROM issued_records r
        JOIN components c ON r.component_id = c.component_id 
        WHERE r.is_returned = 0 AND r.return_date < ?
    ");
    $stmt->execute([$today]);
    $overdueStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($overdueStudents) == 0) {
        sendResponse(true, 'No overdue students to alert.');
    }

    // Telegram ke liye ek summary message banana
    $message = "*LAB MANAGER - OVERDUE ALERT!*\n\n";
    $message .= "Total overdue items: *" . count($overdueStudents) . "*\n";
    $message .= "---------------------------------\n\n";
    
    $count = 1;
    foreach ($overdueStudents as $student) {
        $message .= "$count. *Student:* " . $student['student_name'] . "\n";
        $message .= "   *Item:* " . $student['component_name'] . "\n";
        $message .= "   *Due Date:* " . $student['return_date'] . "\n\n";
    }
    
    // Telegram message bhejna
    $telegramSent = sendTelegramMessage($token, $chatId, $message);

    if ($telegramSent) {
        sendResponse(true, "Successfully sent Telegram alert for " . count($overdueStudents) . " items.");
    } else {
        sendResponse(false, "Alerts processed, but failed to send Telegram message. (Check token/chat_id in api.php)");
    }
}

?>