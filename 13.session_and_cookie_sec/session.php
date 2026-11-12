<?php

$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');


ini_set('session.use_strict_mode', '1');   
ini_set('session.use_only_cookies', '1');  


session_set_cookie_params([
    'lifetime' => 0,           
    'path'     => '/',
    'domain'   => '',
    'secure'   => $is_https,   
    'httponly' => true,        
    'samesite' => 'Strict',    
]);

session_start();



setcookie('demo_plain', 'visible-to-js', [
    'path'     => '/',
    'httponly' => false,
    'samesite' => 'Lax',
]);


if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$action = $_GET['action'] ?? 'show';

if ($action === 'login') {
    
    $old_id = session_id();
    
    session_regenerate_id(true);

    $_SESSION['user']         = 'demo_user';
    $_SESSION['logged_in_at'] = date('c');
    $_SESSION['old_id']       = $old_id;
    $_SESSION['new_id']       = session_id();
    $_SESSION['csrf']         = bin2hex(random_bytes(32));

    header('Location: session.php?action=show');
    exit;
}


if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
                  $params['path'], $params['domain'],
                  $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: session.php?action=show');
    exit;
}


if ($action === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'session_name'  => session_name(),
        'session_id'    => session_id(),
        'logged_in_as'  => $_SESSION['user'] ?? null,
        'old_id'        => $_SESSION['old_id'] ?? null,
        'new_id'        => $_SESSION['new_id'] ?? null,
        'cookie_params' => session_get_cookie_params(),
        'cookies_seen'  => $_COOKIE,
    ], JSON_PRETTY_PRINT);
    exit;
}


$user          = $_SESSION['user'] ?? null;
$old_id        = $_SESSION['old_id'] ?? null;
$new_id        = $_SESSION['new_id'] ?? null;
$cookie_params = session_get_cookie_params();
$current_id    = session_id();
$csrf          = $_SESSION['csrf'];

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Secure session demo</title>
<style>
body { font-family: monospace; max-width: 820px; margin: 2em auto; padding: 0 1em; }
table { border-collapse: collapse; width: 100%; margin: 1em 0; }
td, th { border: 1px solid #ccc; padding: 6px 10px; text-align: left; }
th { background: #eee; }
.ok { color: #080; }
.bad { color: #c00; }
pre { background: #f4f4f4; padding: 10px; overflow-x: auto; }
</style>
</head>
<body>

<h1>Secure session demo</h1>

<table>
<tr><th>Setting</th><th>Value</th></tr>
<tr><td>Session name</td><td><?= htmlspecialchars(session_name()) ?></td></tr>
<tr><td>Current session ID</td><td><?= htmlspecialchars($current_id) ?></td></tr>
<tr><td>Logged in as</td><td><?= $user ? htmlspecialchars($user) : '<em>guest</em>' ?></td></tr>
<tr><td>Cookie lifetime</td><td><?= (int)$cookie_params['lifetime'] ?></td></tr>
<tr><td>Secure flag</td><td><?= $cookie_params['secure'] ? 'on' : 'off' ?></td></tr>
<tr><td>HttpOnly</td><td class="ok"><?= $cookie_params['httponly'] ? 'on ✓' : 'off ✗' ?></td></tr>
<tr><td>SameSite</td><td><?= htmlspecialchars($cookie_params['samesite'] ?? '') ?></td></tr>
</table>

<?php if ($old_id && $new_id && $old_id !== $new_id): ?>
<h2>Session fixation defence</h2>
<p>On login, the session ID was regenerated:</p>
<pre>before login: <?= htmlspecialchars($old_id) ?>

after login:  <?= htmlspecialchars($new_id) ?></pre>
<p class="ok">✓ Different IDs. A fixation attacker's captured ID now points to a deleted session.</p>
<?php endif; ?>

<h2>JavaScript test — can client-side code read the session cookie?</h2>
<p>Press the button. The result should show <code>demo_plain</code> but <strong>not</strong> <code>PHPSESSID</code>.</p>
<button onclick="runTest()">Check document.cookie</button>
<pre id="jsout">(press the button)</pre>

<script>
function runTest() {
    const raw = document.cookie;
    const out = document.getElementById('jsout');
    if (raw === '') {
        out.textContent = 'document.cookie is empty.\n✓ HttpOnly is working.';
        return;
    }
    if (raw.includes('PHPSESSID')) {
        out.textContent = '⚠ LEAK — PHPSESSID visible to JavaScript:\n' + raw;
    } else {
        out.textContent = 'document.cookie shows:\n' + raw +
            '\n\n✓ No PHPSESSID here. HttpOnly blocks JS from reading it.\n' +
            '   (demo_plain is visible because it was set WITHOUT HttpOnly.)';
    }
}
</script>

<h2>Actions</h2>
<?php if ($user): ?>
<p><a href="?action=logout">Log out</a> — clears session data and the cookie</p>
<?php else: ?>
<p><a href="?action=login">Log in</a> — regenerates the session ID</p>
<?php endif; ?>

<p><a href="insecure.php">Visit insecure.php</a> → an unprotected cookie in the same browser.</p>

<h2>Set-Cookie headers you should see in DevTools → Network</h2>
<pre>Set-Cookie: PHPSESSID=<?= htmlspecialchars($current_id) ?>; path=/; HttpOnly; SameSite=Strict
Set-Cookie: demo_plain=visible-to-js; path=/; SameSite=Lax</pre>
<p>Only the second one appears in <code>document.cookie</code>.</p>

<h2>CSRF token (per-session)</h2>
<pre><?= htmlspecialchars($csrf) ?></pre>
<p>In a real login form this would be a hidden field, compared on POST. Together with SameSite=Strict, it's belt-and-suspenders against CSRF.</p>

</body>
</html>