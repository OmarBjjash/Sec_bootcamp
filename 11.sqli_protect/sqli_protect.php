<?php

header("Content-Type: application/json");


try {
    $pdo = new PDO("sqlite::memory:");
    
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $err) {
    
    
    http_response_code(500);
    echo json_encode(["error" => "database unavailable"]);
    exit;
}


$pdo->exec("
    CREATE TABLE users (
        id       INTEGER PRIMARY KEY,
        username TEXT    NOT NULL UNIQUE,
        password TEXT    NOT NULL,
        role     TEXT    NOT NULL
    )
");
$pdo->exec("
    INSERT INTO users (username, password, role) VALUES
        ('admin',  '\$2y\$10\$abcdefghijklmnopqrstuv', 'admin'),
        ('alice',  '\$2y\$10\$qrstuvwxyzabcdefghijklmn', 'user'),
        ('bob',    '\$2y\$10\$ijklmnopqrstuvwxyzabcdef', 'user')
");



$username = $_GET["username"] ?? null;
$mode     = $_GET["mode"] ?? "safe";

if ($username === null) {
    http_response_code(400);
    echo json_encode([
        "error"  => "missing ?username=",
        "hint"   => "try ?username=admin&mode=safe",
    ]);
    exit;
}

if (!in_array($mode, ["safe", "unsafe"], true)) {
    http_response_code(400);
    echo json_encode(["error" => "mode must be 'safe' or 'unsafe'"]);
    exit;
}


if ($mode === "unsafe") {
    
    $query = "SELECT id, username, role FROM users WHERE username = '$username'";

    try {
        $rows = $pdo->query($query)->fetchAll();
    } catch (PDOException $err) {
        
        
        
        echo json_encode([
            "mode"   => "unsafe",
            "query"  => $query,
            "error"  => $err->getMessage(),
            "leak"   => "the query text above reveals the schema and the injection point",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        "mode"  => "unsafe",
        "query" => $query,
        "rows"  => $rows,
        "count" => count($rows),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}



$statement = $pdo->prepare(
    "SELECT id, username, role FROM users WHERE username = :username"
);
$statement->execute([":username" => $username]);
$rows = $statement->fetchAll();

echo json_encode([
    "mode"  => "safe",
    "query" => "SELECT id, username, role FROM users WHERE username = :username",
    "rows"  => $rows,
    "count" => count($rows),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);



/* 
* Safe usage :
* 1    curl -s "http://localhost/sqli.php?username=admin&mode=safe"   | python -m json.tool
*
* unsafe
* 2   curl -s "http://localhost/sqli.php?username=admin&mode=unsafe" | python -m json.tool  
*/