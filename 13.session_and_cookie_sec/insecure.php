<?php

setcookie('PHPSESSID_INSECURE', 'readable-by-any-script', [
    'path'     => '/',
    'httponly' => false,   
    'samesite' => 'Lax',
]);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Insecure cookie demo</title>
<style>
body { font-family: monospace; max-width: 820px; margin: 2em auto; padding: 0 1em; }
pre { background: #f4f4f4; padding: 10px; overflow-x: auto; }
.ok { color: #080; }
.bad { color: #c00; }
</style>
</head>
<body>

<h1>Insecure cookie</h1>
<p>This page sets <code>PHPSESSID_INSECURE</code> with <strong class="bad">HttpOnly = false</strong>.</p>
<p>Click the button — JavaScript can read it directly.</p>

<button onclick="runTest()">Check document.cookie</button>
<pre id="jsout">(press the button)</pre>

<script>
function runTest() {
    document.getElementById('jsout').textContent =
        'document.cookie = ' + JSON.stringify(document.cookie, null, 2) +
        '\n\n⚠ Any XSS on this domain can read PHPSESSID_INSECURE\n' +
        '   and send it to an attacker. That is the whole class of bug.';
}
</script>

<p>Now <a href="session.php">go to session.php</a> and run the same test — the real session cookie won't appear.</p>

</body>
</html>