<?php

namespace KVStore;

use KVStore\Controllers\BucketController;
use KVStore\Controllers\ObjectController;
use KVStore\Controllers\RecoverController;
use KVStore\Controllers\WelcomeController;

include "vendor/autoload.php";

// Router::setController("/{bucket}/{key}", ObjectController::class);
// Router::setController("/{bucket}", BucketController::class);

Router::get("/", [WelcomeController::class, "index"]);

Router::post("/recover", [RecoverController::class, "recover"]);

Router::post("/", [BucketController::class, "createBucket"]);
Router::get("/{bucket}", [BucketController::class, "listObjects"]);
Router::post("/{bucket}", [BucketController::class, "createObject"]);

Router::get("/{bucket}/{key}/meta", [ObjectController::class, "getMeta"]);
Router::get("/{bucket}/{key}", [ObjectController::class, "get"]);
Router::put("/{bucket}/{key}", [ObjectController::class, "createOrUpdate"]);
Router::post("/{bucket}/{key}", [ObjectController::class, "createOrUpdate"]);
Router::delete("/{bucket}/{key}", [ObjectController::class, "delete"]);
Router::method("head", "/{bucket}/{key}", [ObjectController::class, "head"]);
Router::method("patch", "/{bucket}/{key}", [ObjectController::class, "patch"]);

try {
    Router::run();
} catch (AuthException $e) {
    echo $e->getMessage();
} catch (\Exception $e) {
    header("HTTP/1.1 500 Bad Request");
    if ($_SERVER['REQUEST_METHOD'] === "HEAD") {
        // header("X-Exception: " . str_replace("\n", "; ", $e->getMessage()));
    } else {
        echo $e->getMessage();
    }
}
