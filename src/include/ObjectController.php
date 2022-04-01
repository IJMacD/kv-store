<?php


class ObjectController extends BaseController {
    public function get ($bucket, $key) {
        Auth::checkBucketAuth($bucket, "read");

        $bucket = Bucket::get($bucket);
        $object = $bucket->getObject($key);

        if (is_null($object)) {
            return 404;
        }

        $this->response->header("Last-Modified", $object->created->format("r"));

        return $this->response->autoContent($object->value);
    }

    public function getMeta ($bucket, $key) {
        Auth::checkBucketAuth($bucket, "read");

        $bucket = Bucket::get($bucket);
        $object = $bucket->getObject($key);

        if (is_null($object)) {
            return 404;
        }

        $this->response->header("Last-Modified", $object->created->format("r"));

        return $this->response->autoContent($object);
    }

    public function create ($bucket, $key) {
        Auth::checkBucketAuth($bucket, "create");

        $bucket = Bucket::get($bucket);
        return $bucket->createObject($key, $this->request->getBody()) ?
            201 : 500;
    }


    public function update ($bucket, $key) {
        Auth::checkBucketAuth($bucket, "edit");

        $bucket = Bucket::get($bucket);
        return $bucket->editObject($key, $this->request->getBody()) ?
            200 : 500;
    }


    public function delete ($bucket, $key) {
        Auth::checkBucketAuth($bucket, "delete");

        $bucket = Bucket::get($bucket);

        return $bucket->deleteObject($key) ? 200 : 500;
    }
}