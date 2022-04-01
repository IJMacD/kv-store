<?php

class BucketController extends BaseController {
    public function listObjects ($bucket) {
        Auth::checkBucketAuth($bucket, "list");

        $bucket = Bucket::get($bucket);

        $since = $this->request->getQueryParam("since");

        $this->response->header("Last-Modified", $bucket->getLastModifiedDate()->format("r"));

        $field = $this->request->getQueryParam("field");

        if (is_null($field)) {
            $objects = $bucket->getObjects($since);

            return $this->response->autoContent($objects);
        }

        if ($field === "value") {
            $objects = $bucket->getObjects($since);

            $values = array_map(function ($o) { return $o->value; }, $objects);

            return $this->response->autoContent($values);
        }

        if ($field === "key") {
            $keys = $bucket->getObjectKeys($since);

            // Special for csv so we can set header
            if ($this->request->isAccepted("text/csv")) {
                return $this->response->csv($keys, ["key"]);
            }

            return $this->response->autoContent($keys);
        }

        if ($field === "created") {
            $objects = $bucket->getObjects($since);


            // Special for csv so we can set header
            if ($this->request->isAccepted("text/csv")) {
                // Response::csv() extracts fields from objects itself
                return $this->response->csv($objects, ["created"]);
            }

            $values = array_map(function ($o) { return (string)$o->created; }, $objects);

            return $this->response->autoContent($values);
        }

        return 400;
    }

    public function createObject ($bucket) {
        Auth::checkBucketAuth($bucket, "create");

        $key = self::generateNewKey($bucket);

        $b = Bucket::get($bucket);

        if ($b->createObject($key, $this->request->getBody())) {
            return $this->response->statusCode(201)->header("Location", "/$bucket/$key");
        }

        return 500;
    }

    private static function generateNewKey ($bucket_name) {
        $bucket = Bucket::get($bucket_name);
        $existing_keys = $bucket->getObjectKeys();

        $key = uniqid();
        while (in_array($key, $existing_keys)) {
            $key = uniqid();
        }

        return $key;
    }
}