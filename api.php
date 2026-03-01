<?php
// ============================================================
// api.php — BankLab REST API
// Endpoints intentionally lack proper authorization on some
// routes — suitable for API security testing practice.
//
// Usage:  GET/POST /bank-lab/api.php?endpoint=<name>
// Format: JSON responses
// ============================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');         // wide-open CORS — intentional
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key');

require_once 'db.php';
session_start();

// Simple static API keys (no hashing — intentional weak storage)
$api_keys = [
    'sk_live_banklab_alice_1a2b3c4d' => 1,
    'sk_live_banklab_bob_9z8y7x6w'   => 2,
    'sk_live_banklab_carol_q1w2e3r4'  => 3,
    'sk_live_banklab_dave_m5n6o7p8'  => 4,
];

function json_ok($data, $code = 200) {
    http_response_code($code);
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}
function json_err($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

// Resolve authenticated user from API key header
$auth_user_id = null;
$headers = getallheaders();
$api_key  = $headers['X-Api-Key'] ?? ($_GET['api_key'] ?? '');
if ($api_key && isset($api_keys[$api_key])) {
    $auth_user_id = $api_keys[$api_key];
}

$endpoint = $_GET['endpoint'] ?? '';
$method   = $_SERVER['REQUEST_METHOD'];
$body     = json_decode(file_get_contents('php://input'), true) ?? [];

// ──────────────────────────────────────────────
// ROUTE DISPATCHER
// ──────────────────────────────────────────────
switch ($endpoint) {

    // ── GET /api.php?endpoint=info ─────────────
    // Public — no auth required
    case 'info':
        json_ok([
            'name'    => 'BankLab API',
            'version' => '1.0.0',
            'base'    => 'http://localhost/bank-lab/api.php',
            'endpoints' => [
                'GET  ?endpoint=info'                          => 'API info (public)',
                'GET  ?endpoint=ping'                          => 'Health check (public)',
                'GET  ?endpoint=users'                         => 'List all users (authenticated)',
                'GET  ?endpoint=account&id=<n>'                => 'Get account by ID — no auth check (IDOR)',
                'GET  ?endpoint=balance'                       => 'Get own balance (authenticated)',
                'GET  ?endpoint=transactions&account_id=<n>'   => 'Get transactions — no auth check (IDOR)',
                'POST ?endpoint=transfer'                      => 'Transfer money (authenticated)',
                'GET  ?endpoint=search&q=<term>'               => 'Search users by name — SQLi (no auth)',
                'POST ?endpoint=update_profile'                => 'Update profile — Mass Assignment (authenticated)',
            ],
            'auth'  => 'Pass X-Api-Key header with your API key',
            'note'  => 'Demo keys visible in /api.php source',
        ]);

    // ── GET ?endpoint=users ────────────────────
    // Requires auth — but returns all users including passwords (intentional)
    case 'users':
        if (!$auth_user_id) json_err('Authentication required. Pass X-Api-Key header.', 401);
        $res  = mysqli_query($conn, "SELECT id, username, full_name, email, avatar_color, created_at FROM users");
        $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
        json_ok($rows);

    // ── GET ?endpoint=account&id=N ─────────────
    // INTENTIONAL IDOR: no check that id belongs to auth_user
    case 'account':
        $id  = (int)($_GET['id'] ?? 0);
        if ($id <= 0) json_err('Pass ?id=<account_id>');
        $row = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT accounts.*, users.full_name, users.email
               FROM accounts JOIN users ON accounts.user_id = users.id
              WHERE accounts.id = $id"
        ));
        if (!$row) json_err('Account not found.', 404);
        json_ok($row);  // returns sensitive data for any account

    // ── GET ?endpoint=balance ──────────────────
    case 'balance':
        if (!$auth_user_id) json_err('Authentication required.', 401);
        $row = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT accounts.id, account_number, account_type, balance
               FROM accounts WHERE user_id = $auth_user_id LIMIT 1"
        ));
        json_ok($row);

    // ── GET ?endpoint=transactions&account_id=N ─
    // INTENTIONAL IDOR + info disclosure
    case 'transactions':
        $acc_id = (int)($_GET['account_id'] ?? 0);
        if ($acc_id <= 0) json_err('Pass ?account_id=<n>');
        $res  = mysqli_query($conn,
            "SELECT t.id, t.amount, t.memo, t.transaction_date,
                    u_from.full_name AS from_name, af.account_number AS from_acc,
                    u_to.full_name   AS to_name,   at2.account_number AS to_acc
               FROM transactions t
               JOIN accounts af  ON t.from_account_id = af.id
               JOIN accounts at2 ON t.to_account_id   = at2.id
               JOIN users u_from ON af.user_id  = u_from.id
               JOIN users u_to   ON at2.user_id = u_to.id
              WHERE t.from_account_id = $acc_id OR t.to_account_id = $acc_id
              ORDER BY t.transaction_date DESC"
        );
        json_ok(mysqli_fetch_all($res, MYSQLI_ASSOC));

    // ── POST ?endpoint=transfer ────────────────
    // INTENTIONAL CSRF via API: no CSRF token, wide CORS
    case 'transfer':
        if ($method !== 'POST') json_err('Use POST method.', 405);
        if (!$auth_user_id)     json_err('Authentication required.', 401);

        $to_id  = (int)  ($body['to_account_id'] ?? 0);
        $amount = (float)($body['amount']          ?? 0);
        $memo   = $body['memo'] ?? '';  // stored raw — XSS

        if ($to_id <= 0 || $amount <= 0) json_err('to_account_id and amount are required.');

        $sender = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT * FROM accounts WHERE user_id = $auth_user_id LIMIT 1"
        ));
        if (!$sender) json_err('Sender account not found.', 404);
        if ($amount > $sender['balance']) json_err('Insufficient balance.');

        $from_id = $sender['id'];
        mysqli_query($conn, "UPDATE accounts SET balance = balance - $amount WHERE id = $from_id");
        mysqli_query($conn, "UPDATE accounts SET balance = balance + $amount WHERE id = $to_id");
        mysqli_query($conn,
            "INSERT INTO transactions (from_account_id, to_account_id, amount, memo)
             VALUES ($from_id, $to_id, $amount, '$memo')"
        );
        json_ok(['message' => 'Transfer successful.', 'from' => $from_id, 'to' => $to_id, 'amount' => $amount], 201);

    // ── GET ?endpoint=search&q=term ───────────
    // No auth required — user enumeration vulnerability
    case 'search':
        $q   = $_GET['q'] ?? '';
        // Intentional SQL injection in search
        $res = mysqli_query($conn, "SELECT id, username, full_name, email FROM users WHERE full_name LIKE '%$q%'");
        json_ok(mysqli_fetch_all($res, MYSQLI_ASSOC));

    // ── POST ?endpoint=update_profile ──────────
    // MASS ASSIGNMENT: any JSON field is blindly used in UPDATE
    // Attack: {"field": "password", "value": "hacked"} or {"field": "email", "value": "attacker@x.com"}
    case 'update_profile':
        if ($method !== 'POST') json_err('Use POST method.', 405);
        if (!$auth_user_id)     json_err('Authentication required.', 401);

        $field = $body['field'] ?? '';   // user-controlled column name — no allowlist
        $value = $body['value'] ?? '';   // user-controlled value

        if (empty($field)) json_err('Provide field and value in JSON body.');

        // MASS ASSIGNMENT: field name inserted directly into query
        mysqli_query($conn, "UPDATE users SET $field = '$value' WHERE id = $auth_user_id");
        $updated = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT id, username, full_name, email, created_at FROM users WHERE id = $auth_user_id"
        ));
        json_ok(['message' => "Profile field '$field' updated.", 'user' => $updated]);

    // ── GET ?endpoint=ping ─────────────────────
    case 'ping':
        json_ok(['message' => 'pong', 'time' => date('Y-m-d H:i:s')]);

    default:
        json_err("Unknown endpoint '$endpoint'. Visit ?endpoint=info for available endpoints.", 404);
}
?>
