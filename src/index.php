<?php

include "vendor/autoload.php";
include "include/conf.php";

// Router::setController("/{bucket}/{key}", ObjectController::class);
// Router::setController("/{bucket}", BucketController::class);

Router::get("/god", 405);
Router::post("/god", [GodController::class, "handle"]);

Router::get("/{bucket}", [BucketController::class, "listObjects"]);
Router::post("/{bucket}", [BucketController::class, "createObject"]);

Router::get("/{bucket}/{key}/meta", [ObjectController::class, "getMeta"]);
Router::get("/{bucket}/{key}", [ObjectController::class, "get"]);
Router::put("/{bucket}/{key}", [ObjectController::class, "create"]);
Router::post("/{bucket}/{key}", [ObjectController::class, "update"]);
Router::delete("/{bucket}/{key}", [ObjectController::class, "delete"]);

try {
    Router::run();
} catch (AuthException $e) {
    echo $e->getMessage();
} catch (Exception $e) {
    header("HTTP/1.1 400 Bad Request");
    echo $e->getMessage();
}