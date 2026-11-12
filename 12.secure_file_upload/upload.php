<?php

header("Content-Type: application/json");


const MAX_SIZE = 2 * 1024 * 1024;   
const UPLOAD_DIR = "/opt/lampp/upload_storage";

const ALLOWED_EXTENSIONS = ["jpg", "jpeg", "png", "pdf"];

const ALLOWED_MIME = [
    "jpg"  => ["image/jpeg"],
    "jpeg" => ["image/jpeg"],
    "png"  => ["image/png"],
    "pdf"  => ["application/pdf"],
];


const UPLOAD_ERRORS = [
    UPLOAD_ERR_INI_SIZE   => "file exceeds upload_max_filesize in php.ini",
    UPLOAD_ERR_FORM_SIZE  => "file exceeds MAX_FILE_SIZE form field",
    UPLOAD_ERR_PARTIAL    => "file was only partially uploaded",
    UPLOAD_ERR_NO_FILE    => "no file was uploaded",
    UPLOAD_ERR_NO_TMP_DIR => "server has no temp directory",
    UPLOAD_ERR_CANT_WRITE => "server failed to write the file to disk",
    UPLOAD_ERR_EXTENSION  => "a PHP extension blocked the upload",
];


function respond($status, $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}


function fail($status, $message, $extra = [])
{
    respond($status, array_merge(["error" => $message], $extra));
}


$method = $_SERVER["REQUEST_METHOD"];
$start_time = microtime(true);


if ($method === "GET") {
    header("Content-Type: text/html; charset=utf-8");
    echo <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Upload</title></head>
<body>
<h1>Upload a file</h1>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="uploaded_file" required>
    <button type="submit">Upload</button>
</form>
<p>Accepted: jpg, jpeg, png, pdf &mdash; max 2 MB.</p>
</body>
</html>
HTML;
    exit;
}

if ($method !== "POST") {
    header("Allow: GET, POST");
    fail(405, "method not allowed", ["method" => $method]);
}



if (!isset($_FILES["uploaded_file"])) {
    fail(400, "no file uploaded (form field must be named 'uploaded_file')");
}

$file = $_FILES["uploaded_file"];


if ($file["error"] !== UPLOAD_ERR_OK) {
    $reason = UPLOAD_ERRORS[$file["error"]] ?? "unknown upload error";
    
    $status = ($file["error"] === UPLOAD_ERR_INI_SIZE || $file["error"] === UPLOAD_ERR_FORM_SIZE) ? 413 : 400;
    fail($status, $reason, ["code" => $file["error"]]);
}



if ($file["size"] === 0) {
    fail(400, "file is empty");
}

if ($file["size"] > MAX_SIZE) {
    fail(413, "file too large", ["max" => MAX_SIZE, "got" => $file["size"]]);
}



$original_name = $file["name"];
$ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

if ($ext === "") {
    fail(400, "file has no extension");
}

if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
    fail(400, "extension not allowed", [
        "extension" => $ext,
        "allowed"   => ALLOWED_EXTENSIONS,
    ]);
}



$finfo = new finfo(FILEINFO_MIME_TYPE);
$detected_mime = $finfo->file($file["tmp_name"]);

if (!in_array($detected_mime, ALLOWED_MIME[$ext], true)) {
    fail(400, "content does not match extension", [
        "extension"     => $ext,
        "expected_mime" => ALLOWED_MIME[$ext],
        "detected_mime" => $detected_mime,
    ]);
}


$random_name = bin2hex(random_bytes(16)) . "." . $ext;


if (!is_dir(UPLOAD_DIR)) {
    
    
    if (!@mkdir(UPLOAD_DIR, 0750, true)) {
        fail(500, "storage directory missing and could not be created", [
            "path" => UPLOAD_DIR,
            "hint" => "create it manually: sudo mkdir -p " . UPLOAD_DIR .
                      " && sudo chown daemon:daemon " . UPLOAD_DIR,
        ]);
    }
}

if (!is_writable(UPLOAD_DIR)) {
    fail(500, "storage directory is not writable by the web server", [
        "path" => UPLOAD_DIR,
        "hint" => "sudo chown daemon:daemon " . UPLOAD_DIR,
    ]);
}

$destination = UPLOAD_DIR . "/" . $random_name;



if (!move_uploaded_file($file["tmp_name"], $destination)) {
    fail(500, "failed to move uploaded file into storage");
}


@chmod($destination, 0640);

$took = (microtime(true) - $start_time) * 1000;

respond(200, [
    "message"       => "upload accepted",
    "original_name" => $original_name,
    "stored_as"     => $random_name,
    "size"          => $file["size"],
    "extension"     => $ext,
    "detected_mime" => $detected_mime,
    "sha256"        => hash_file("sha256", $destination),
    "elapsed_ms"    => round($took, 2),
]);