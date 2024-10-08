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
    public function listObjects($bucket)
    {
        Auth::checkBucketAuth($bucket, "list");

        $bucket = Bucket::get($bucket);

        $since = $this->request->getQueryParam("since");
        $limit = $this->request->getQueryParam("limit", 10000);
        $prefix = $this->request->getQueryParam("prefix");

        $this->response->header("Last-Modified", $bucket->getLastModifiedDate()->format("r"));

        $field = $this->request->getQueryParam("field");

        if ($field === "key") {
            $keys = $bucket->getObjectKeys($since, $limit, $prefix);

            // Special for csv so we can set header
            if ($this->request->isAccepted("text/csv", true)) {
                return $this->response->csv($keys, ["key"]);
            }

            return $this->response->autoContent($keys);
        }

        $objects = $bucket->getObjects($since, $limit, $prefix);

        if (is_null($field)) {
            return $this->response->autoContent($objects);
        }

        if ($field === "value") {
            $values = array_map(function ($o) {
                return $o->value;
            }, $objects);

            return $this->response->autoContent($values);
        }

        if ($field === "created") {
            // Special for csv so we can set header
            if ($this->request->isAccepted("text/csv", true)) {
                // Response::csv() extracts fields from objects itself
                return $this->response->csv($objects, ["created"]);
            }

            $values = array_map(function ($o) {
                return (string)$o->created;
            }, $objects);

            return $this->response->autoContent($values);
        }

        return 400;
    }

    public function createBucket()
    {
        // Max length = 50 chars (25 bytes)
        $name = bin2hex(random_bytes(12));

        $email = $this->request->getRequestParam("email");
        $admin_key = $this->request->getRequestParam("admin_key");
        $read_key = $this->request->getRequestParam("read_key");
        $write_key = $this->request->getRequestParam("write_key");

        if (Bucket::create($name, $email)) {

            // If an explicit secret has not been given then auto-generate one
            if (!$admin_key) {
                $admin_key = bin2hex(random_bytes(18));
            }

            $bucket_auth = new BucketAuth();
            $bucket_auth->list = true;
            $bucket_auth->read = true;
            $bucket_auth->create = true;
            $bucket_auth->edit = true;
            $bucket_auth->delete = true;
            $bucket_auth->admin = true;
            Auth::addBucketAuth($name, "bearer", $bucket_auth, secret: $admin_key);

            if ($email) {
                try {
                    Email::send(BucketCreated::class, $email, ["name" => $name, "secret"  => $admin_key]);
                } catch (Exception $e) {
                    // TODO: handle better
                    // Couldn't send email
                }
            }

            // If a write key has been given, generate auth for writing
            if ($write_key) {
                $bucket_auth = new BucketAuth();
                $bucket_auth->create = true;
                $bucket_auth->edit = true;
                $bucket_auth->delete = true;

                if ($read_key === $write_key) {
                    $bucket_auth->list = true;
                    $bucket_auth->read = true;
                }

                Auth::addBucketAuth($name, "bearer", $bucket_auth, secret: $write_key);
            }

            // If a read key has been given, generate auth for reading
            // (If this hasn't already been taken care of)
            if ($read_key && $read_key != $write_key) {
                $bucket_auth = new BucketAuth();
                $bucket_auth->list = true;
                $bucket_auth->read = true;
                Auth::addBucketAuth($name, "bearer", $bucket_auth, secret: $read_key);
            }

            // If none of secret, read_key, or write_key has been given, then
            // the bucket defaults to public access
            if (!$write_key && !$read_key) {
                $bucket_auth = new BucketAuth();
                $bucket_auth->list = true;
                $bucket_auth->read = true;
                $bucket_auth->create = true;
                $bucket_auth->edit = true;
                $bucket_auth->delete = true;

                Auth::addBucketAuth($name, "public", $bucket_auth);
            } else if (!$read_key) {
                $bucket_auth = new BucketAuth();
                $bucket_auth->list = true;
                $bucket_auth->read = true;

                Auth::addBucketAuth($name, "public", $bucket_auth);
            }

            return $this->response->statusCode(201)->header("Location", "/$name");
        }

        return 500;
    }

    public function createObject($bucket)
    {
        Auth::checkBucketAuth($bucket, "create");

        $key = self::generateNewKey($bucket);

        $b = Bucket::get($bucket);

        if ($b->createObject($key, $this->request->getBody())) {
            return $this->response->statusCode(201)->header("Location", "/$bucket/$key");
        }

        return 500;
    }

    private static function generateNewKey($bucket_name)
    {
        $bucket = Bucket::get($bucket_name);
        $existing_keys = $bucket->getObjectKeys();

        $key = uniqid();
        while (in_array($key, $existing_keys)) {
            $key = uniqid();
        }

        return $key;
    }
}
