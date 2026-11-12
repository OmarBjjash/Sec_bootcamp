<?php

header("Content-Type: application/json");


function respond($status, $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}



function save_store($path, $store)
{
    $written = file_put_contents($path, json_encode($store, JSON_PRETTY_PRINT));
    if ($written === false) {
        respond(500, ["error" => "failed to persist store", "path" => $path]);
    }
}


$method = $_SERVER["REQUEST_METHOD"];



if (function_exists("getallheaders")) {
    $headers = getallheaders();
} else {
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, "HTTP_") === 0) {
            $name = str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", substr($key, 5)))));
            $headers[$name] = $value;
        }
    }
}


$auth = $headers["Authorization"] ?? $headers["authorization"] ?? null;

$raw_body = file_get_contents("php://input");
$data = null;
if ($raw_body !== "") {
    $data = json_decode($raw_body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        respond(400, [
            "error"  => "invalid JSON body",
            "detail" => json_last_error_msg(),
        ]);
    }
}





$store_path = __DIR__ . "/items.json";
$store = [];
if (file_exists($store_path)) {
    $decoded = json_decode(file_get_contents($store_path), true);
    if (is_array($decoded)) {
        $store = $decoded;
    }
}



switch ($method) {

    case "GET":
        
        if (isset($_GET["id"])) {
            $id = $_GET["id"];
            if (!isset($store[$id])) {
                respond(404, ["error" => "item not found", "id" => $id]);
            }
            respond(200, ["item" => $store[$id], "auth" => $auth]);
        }
        respond(200, [
            "method"  => "GET",
            "count"   => count($store),
            "items"   => $store,
            "auth"    => $auth ?? "anonymous",
            "headers" => $headers,
        ]);
        break;

    case "POST":
        if (!is_array($data) || !isset($data["name"])) {
            respond(400, ["error" => "field 'name' is required in the JSON body"]);
        }
        $id = uniqid("item_", true);
        $store[$id] = [
            "id"      => $id,
            "name"    => $data["name"],
            "created" => date("c"),
        ];
        save_store($store_path, $store);
        respond(201, [
            "message" => "item created",
            "item"    => $store[$id],
        ]);
        break;

    case "PUT":
        
        $id = $data["id"] ?? ($_GET["id"] ?? null);
        if (!$id) {
            respond(400, ["error" => "missing 'id' in body or ?id= query"]);
        }
        if (!isset($store[$id])) {
            respond(404, ["error" => "item not found", "id" => $id]);
        }
        if (isset($data["name"])) {
            $store[$id]["name"] = $data["name"];
        }
        $store[$id]["updated"] = date("c");
        save_store($store_path, $store);
        respond(200, [
            "message" => "item updated",
            "item"    => $store[$id],
        ]);
        break;

    case "DELETE":
        $id = $data["id"] ?? ($_GET["id"] ?? null);
        if (!$id) {
            respond(400, ["error" => "missing 'id' in body or ?id= query"]);
        }
        if (!isset($store[$id])) {
            respond(404, ["error" => "item not found", "id" => $id]);
        }
        unset($store[$id]);
        save_store($store_path, $store);
        respond(200, ["message" => "item deleted", "id" => $id]);
        break;

    case "HEAD":
    case "OPTIONS":
        
        header("Allow: GET, POST, PUT, DELETE, OPTIONS");
        http_response_code(204);
        exit;

    default:
        
        header("Allow: GET, POST, PUT, DELETE, OPTIONS");
        respond(405, [
            "error"  => "method not allowed",
            "method" => $method,
        ]);
}


/**
 * 1. Get
 * curl -s http://localhost/rp.php | python -m json.tool
 * 
 * 
 * 2.Post
 * curl -s -X POST http://localhost/rp.php \
  -H "Content-Type: application/json" \
  -d '{"name":"My First Item"}' | python -m json.tool
 * 
 * 
 * 3. Put
 * curl -s -X PUT http://localhost/rp.php \
  -H "Content-Type: application/json" \
  -d '{"id":"id_of_item", "name":"new name"}' | python -m json.tool
 * 
 * 4. Delete
 * curl -s -X DELETE http://localhost/rp.php \
  -H "Content-Type: application/json" \
  -d '{"id":"PASTE_ITEM_ID_HERE"}' | python -m json.tool
 * 
 * 
 * 5. 
 * curl -s "http://localhost/rp.php?id=item_6af621b22c07d1.89925509" | python -m json.tool
 * 
 * 6. 
 * curl -s -X PATCH http://localhost/api.php -i
 * 
 * 7.
 * curl -s http://localhost/rp.php \
 * -H "Authorization: Bearer my-secret-token" | python -m json.tool
 */