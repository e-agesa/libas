<?php
/**
 * Review endpoint for the Libas ul Anwar issue-resolution report.
 *
 * GET  ?action=list          -> {ok, comments: [...], signoffs: [...]}   (emails never returned)
 * POST name,email,text,file  -> add a comment (multipart; file optional)
 * POST type=signoff,...      -> record a client sign-off (requires confirm=on|1)
 *
 * Storage: _review/comments.json, _review/signoffs.json, _review/uploads/
 * Notifications: best-effort PHP mail() to the admin and the reviewer.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$ADMIN_EMAIL = 'twinfusion2023@gmail.com';
$FROM        = 'noreply@libasulanwar.com';
$REPORT_URL  = 'https://libasulanwar.com/report/status-report.html';
$BASE_URL    = 'https://libasulanwar.com/report/';
$MAX_UPLOAD  = 20 * 1024 * 1024; // 20 MB
$ALLOWED_EXT = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','csv','zip'];

$base   = __DIR__ . '/_review';
$updir  = $base . '/uploads';

function ensure_storage($base, $updir) {
    if (!is_dir($updir)) {
        @mkdir($updir, 0755, true);
    }
    // deny direct access to the JSON files + disable script execution in uploads
    $ht = $base . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "<FilesMatch \"\\.json$\">\nRequire all denied\n</FilesMatch>\n");
    }
    $ht2 = $updir . '/.htaccess';
    if (!file_exists($ht2)) {
        @file_put_contents($ht2, "SetHandler none\nSetHandler default-handler\nOptions -ExecCGI\nphp_flag engine off\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8\n");
    }
}

function read_json($file) {
    if (!file_exists($file)) return [];
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function write_json($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function clean_text($s, $max) {
    $s = strip_tags((string) $s);
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
    return trim(mb_substr($s, 0, $max));
}

function send_mail_best_effort($to, $subject, $body, $from) {
    // Sending is best-effort; never block or fail the response on it.
    $headers = "From: Twinfusion Reports <{$from}>\r\n"
             . "Reply-To: {$from}\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    @mail($to, $encSubject, $body, $headers);
}

ensure_storage($base, $updir);
$commentsFile = $base . '/comments.json';
$signoffsFile = $base . '/signoffs.json';

/* ---------------- LIST ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['action'] ?? '') !== 'list') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
        exit;
    }
    $comments = array_map(function ($c) {
        // never expose emails
        return [
            'name'      => $c['name'] ?? '',
            'text'      => $c['text'] ?? '',
            'date'      => $c['date'] ?? '',
            'file'      => $c['file'] ?? null,
            'file_name' => $c['file_name'] ?? null,
        ];
    }, read_json($commentsFile));
    $signoffs = array_map(function ($s) {
        return [
            'name' => $s['name'] ?? '',
            'role' => $s['role'] ?? '',
            'date' => $s['date'] ?? '',
        ];
    }, read_json($signoffsFile));
    echo json_encode(['ok' => true, 'comments' => $comments, 'signoffs' => $signoffs]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

/* ---------------- shared validation ---------------- */
$name  = clean_text($_POST['name'] ?? '', 120);
$email = trim((string) ($_POST['email'] ?? ''));
if ($name === '') {
    echo json_encode(['ok' => false, 'error' => 'Please enter your name.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}
$date = date('j M Y · H:i');

/* ---------------- SIGN-OFF ---------------- */
if (($_POST['type'] ?? '') === 'signoff') {
    $confirm = $_POST['sconfirm'] ?? $_POST['confirm'] ?? '';
    // the page's checkbox is unnamed in FormData unless checked handling — require explicit ack
    if (!in_array(strtolower((string) $confirm), ['on', '1', 'true', 'yes'], true)) {
        echo json_encode(['ok' => false, 'error' => 'Please tick the confirmation box.']);
        exit;
    }
    $role = clean_text($_POST['role'] ?? '', 120);

    $signoffs = read_json($signoffsFile);
    $signoffs[] = ['name' => $name, 'email' => $email, 'role' => $role, 'date' => $date];
    write_json($signoffsFile, $signoffs);

    send_mail_best_effort(
        $GLOBALS['ADMIN_EMAIL'],
        "SIGN-OFF: Libas report signed by {$name}",
        "{$name}" . ($role ? " ({$role})" : '') . " signed off the Libas ul Anwar issue-resolution report.\n\nWhen: {$date}\nEmail: {$email}\nReport: {$GLOBALS['REPORT_URL']}",
        $GLOBALS['FROM']
    );
    send_mail_best_effort(
        $email,
        'Your sign-off on the Libas ul Anwar report was recorded',
        "Dear {$name},\n\nThis confirms your formal sign-off on the Libas ul Anwar POS/Invoicing issue-resolution report ({$date}).\n\nA copy of the report remains available at:\n{$GLOBALS['REPORT_URL']}\n\nThank you,\nTwinfusion",
        $GLOBALS['FROM']
    );

    echo json_encode(['ok' => true]);
    exit;
}

/* ---------------- COMMENT ---------------- */
$text = clean_text($_POST['text'] ?? '', 4000);
if ($text === '') {
    echo json_encode(['ok' => false, 'error' => 'Please write a comment.']);
    exit;
}

$fileUrl = null;
$fileName = null;
if (!empty($_FILES['file']['name']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $orig = (string) $_FILES['file']['name'];
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, $ALLOWED_EXT, true)) {
        echo json_encode(['ok' => false, 'error' => 'File type not allowed. Use images, PDF, Office, CSV or ZIP.']);
        exit;
    }
    if (($_FILES['file']['size'] ?? 0) > $MAX_UPLOAD) {
        echo json_encode(['ok' => false, 'error' => 'File too large (20 MB maximum).']);
        exit;
    }
    $unique = date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $updir . '/' . $unique)) {
        echo json_encode(['ok' => false, 'error' => 'Could not store the file. Try again.']);
        exit;
    }
    $fileUrl  = '_review/uploads/' . $unique;
    $fileName = clean_text($orig, 140);
}

$comments = read_json($commentsFile);
$comments[] = [
    'name' => $name, 'email' => $email, 'text' => $text,
    'file' => $fileUrl, 'file_name' => $fileName, 'date' => $date,
];
write_json($commentsFile, $comments);

send_mail_best_effort(
    $ADMIN_EMAIL,
    "COMMENT: Libas report — {$name}",
    "New comment on the Libas ul Anwar issue-resolution report.\n\nFrom: {$name} <{$email}>\nWhen: {$date}\n\n{$text}\n" . ($fileUrl ? "\nAttachment: {$BASE_URL}{$fileUrl}\n" : '') . "\nReport: {$REPORT_URL}",
    $FROM
);
send_mail_best_effort(
    $email,
    'Your comment on the Libas ul Anwar report was received',
    "Dear {$name},\n\nThank you — your comment on the Libas ul Anwar POS/Invoicing issue-resolution report was received ({$date}) and the team has been notified.\n\nYour comment:\n{$text}\n\nThe report: {$REPORT_URL}\n\nRegards,\nTwinfusion",
    $FROM
);

echo json_encode(['ok' => true]);
