<?php

namespace KVStore\Controllers;

use Exception;
use KVStore\Models\Auth;
use KVStore\Models\Bucket;
use KVStore\BucketAuth;
use KVStore\Email;
use KVStore\Emails\BucketCreated;

class BucketController extends BaseController
{
    public function createBucket()
    {
        // Max length = 50 chars (25 bytes)
        $name = bin2hex(random_bytes(12));

        $email = $this->request->getRequestParam("email");
        $admin_key = $this->request->getRequestParam("admin_key", bin2hex(random_bytes(18)));
        $read_key = $this->request->getRequestParam("read_key");
        $write_key = $this->request->getRequestParam("write_key");

        if (Bucket::create($name, $email, $admin_key, $read_key, $write_key)) {
            if ($email) {
                try {
                    Email::send(BucketCreated::class, $email, ["name" => $name, "secret"  => $admin_key]);
                } catch (Exception $e) {
                    // TODO: handle better
                    // Couldn't send email
                }
            }

            return $this->response->statusCode(201)->header("Location", "/$name");
        }

        return 500;
    }

    public function createObject(string $bucket_name)
    {
        $bucket = Bucket::get($bucket_name);

        if (!$bucket) {
            return 404;
        }

        if (!Auth::checkBucketAuth($bucket, "write")) {
            return 403;
        }

        $key = self::generateNewKey($bucket);

        // Delegate to ObjectController after creating key
        return (new ObjectController())->create($bucket->name, $key);
    }

    public function listObjects(string $bucket_name)
    {
        $bucket = Bucket::get($bucket_name);

        if (!$bucket) {
            return 404;
        }

        if (!Auth::checkBucketAuth($bucket, "read")) {
            return 403;
        }

        $since = $this->request->getQueryParam("since");
        $limit = $this->request->getQueryParam("limit", 10000);
        $prefix = $this->request->getQueryParam("prefix");

        $this->response->header("Last-Modified", $bucket->getLastModifiedDate()->format("r"));

        $keys = $bucket->getObjectKeys($since, $limit, $prefix);

        // Special for csv so we can set header
        if ($this->request->isAccepted("text/csv", true)) {
            return $this->response->csv($keys, ["key"]);
        }

        return $this->response->autoContent($keys);
    }

    private static function generateNewKey(Bucket $bucket)
    {
        $existing_keys = $bucket->getObjectKeys();

        $key = uniqid();
        while (in_array($key, $existing_keys)) {
            $key = uniqid();
        }

        return $key;
    }
}
