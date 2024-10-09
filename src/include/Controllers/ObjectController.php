<?php

namespace KVStore\Controllers;

use KVStore\Models\Auth;
use KVStore\Models\Bucket;

class ObjectController extends BaseController
{
    public function get($bucket, $key)
    {
        Auth::checkBucketAuth($bucket, "read");

        $bucket = Bucket::get($bucket);
        $object = $bucket->getObject($key);

        if (is_null($object)) {
            return 404;
        }

        $this->response->header("Last-Modified", $object->created->format("r"));

        return $this->response->autoContent($object->value, mime_hint: $object->mime);
    }

    public function getMeta($bucket, $key)
    {
        Auth::checkBucketAuth($bucket, "read");

        $bucket = Bucket::get($bucket);
        $object = $bucket->getObject($key);

        if (is_null($object)) {
            return 404;
        }

        $this->response->header("Last-Modified", $object->created->format("r"));

        return $this->response->autoContent($object);
    }

    public function create($bucket, $key)
    {
        Auth::checkBucketAuth($bucket, "create");

        $bucket = Bucket::get($bucket);
        return $bucket->createObject($key, $this->request->getBody()) ?
            201 : 500;
    }


    public function update($bucket, $key)
    {
        Auth::checkBucketAuth($bucket, "edit");

        $bucket = Bucket::get($bucket);
        return $bucket->editObject($key, $this->request->getBody(), $this->request->getHeader("Content-Type")) ?
            200 : 500;
    }

    public function createOrUpdate($bucket, $key)
    {
        Auth::checkBucketAuth($bucket, "create");

        $bucket = Bucket::get($bucket);

        $object = $bucket->getObject($key);

        if ($object) {
            return $bucket->editObject($key, $this->request->getBody(), $this->request->getHeader("Content-Type")) ? 204 : 500;
        }

        return $bucket->createObject($key, $this->request->getBody()) ?
            201 : 500;
    }

    public function delete($bucket, $key)
    {
        Auth::checkBucketAuth($bucket, "delete");

        $bucket = Bucket::get($bucket);

        return $bucket->deleteObject($key) ? 200 : 500;
    }

    public function head($bucket, $key)
    {
        Auth::checkBucketAuth($bucket, "read");

        $bucket = Bucket::get($bucket);
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

    public function patch($bucket, $key)
    {
        Auth::checkBucketAuth($bucket, "edit");

        $bucket = Bucket::get($bucket);

        $delta = (float) $this->request->getBody();

        $number = $bucket->patchNumericObject($key, $delta);

        return $this->response->autoContent($number);
    }
}
