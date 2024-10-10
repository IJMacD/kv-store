<?php

namespace KVStore\Controllers;

use KVStore\Models\Auth;
use KVStore\Models\Bucket;

class ObjectController extends BaseController
{
    public function get(string $bucket_name, string $key)
    {
        $bucket = Bucket::get($bucket_name);

        if (is_null($bucket)) {
            return 404;
        }

        if (!Auth::checkBucketAuth($bucket, "read", $key)) {
            return 403;
        }

        $object = $bucket->getObject($key);

        if (is_null($object)) {
            return 404;
        }

        $this->response->header("Last-Modified", $object->created->format("r"));

        return $this->response->autoContent($object->value, mime_hint: $object->mime);
    }

    public function getMeta(string $bucket_name, string $key)
    {
        $bucket = Bucket::get($bucket_name);

        if (is_null($bucket)) {
            return 404;
        }

        if (!Auth::checkBucketAuth($bucket, "read", $key)) {
            return 403;
        }

        $object = $bucket->getObject($key);

        if (is_null($object)) {
            return 404;
        }

        $this->response->header("Last-Modified", $object->created->format("r"));

        return $this->response->autoContent($object);
    }

    public function create(string $bucket_name, string $key)
    {
        $bucket = Bucket::get($bucket_name);

        if (is_null($bucket)) {
            return 404;
        }

        if (!Auth::checkBucketAuth($bucket, "write", $key)) {
            return 403;
        }

        return $bucket->createObject($key, $this->request->getBody(), $this->request->getHeader("Content-Type")) ?
            201 : 500;
    }


    public function update(string $bucket_name, string $key)
    {
        $bucket = Bucket::get($bucket_name);

        if (is_null($bucket)) {
            return 404;
        }

        if (!Auth::checkBucketAuth($bucket, "write", $key)) {
            return 403;
        }

        return $bucket->editObject($key, $this->request->getBody(), $this->request->getHeader("Content-Type")) ?
            200 : 500;
    }

    public function createOrUpdate(string $bucket_name, string $key)
    {
        $bucket = Bucket::get($bucket_name);

        if (is_null($bucket)) {
            return 404;
        }

        if (!Auth::checkBucketAuth($bucket, "write", $key)) {
            return 403;
        }

        $object = $bucket->getObject($key);

        if ($object) {
            return $bucket->editObject($key, $this->request->getBody(), $this->request->getHeader("Content-Type")) ? 204 : 500;
        }

        return $bucket->createObject($key, $this->request->getBody(), $this->request->getHeader("Content-Type")) ?
            201 : 500;
    }

    public function delete(string $bucket_name, string $key)
    {
        $bucket = Bucket::get($bucket_name);

        if (is_null($bucket)) {
            return 404;
        }

        if (!Auth::checkBucketAuth($bucket, "write", $key)) {
            return 403;
        }

        return $bucket->deleteObject($key) ? 200 : 500;
    }

    public function head(string $bucket_name, string $key)
    {
        $bucket = Bucket::get($bucket_name);

        if (is_null($bucket)) {
            return 404;
        }

        if (!Auth::checkBucketAuth($bucket, "read", $key)) {
            return 403;
        }

        $object = $bucket->getObject($key);

        if (is_null($object)) {
            return 404;
        }

        $this->response->header("Last-Modified", $object->created->format("r"));

        $this->response->autoContent($object->value, mime_hint: $object->mime);

        header("Content-Type: " . $this->response->getContentType());

        header("Content-Length: " . strlen($this->response->getContent()));

        return 200;
    }

    public function patch(string $bucket_name, string $key)
    {
        $bucket = Bucket::get($bucket_name);

        if (is_null($bucket)) {
            return 404;
        }

        if (!Auth::checkBucketAuth($bucket, "write", $key)) {
            return 403;
        }

        $delta = (float) $this->request->getBody();

        $number = $bucket->patchNumericObject($key, $delta);

        return $this->response->autoContent($number);
    }
}
