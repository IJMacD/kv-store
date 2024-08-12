<?php

namespace KVStore\Controllers;

use KVStore\BucketAuth;
use KVStore\Email;
use KVStore\Emails\BucketRecover;
use KVStore\Models\Auth;
use KVStore\Models\Bucket;

class RecoverController extends BaseController
{
    /**
     * POST /recover
     * email=<xxx>&label=<yyy>&callback=<zzz>
     *
     * Sends email to registered email address for any matching buckets with new
     * auth key.
     */
    public function recover()
    {
        $email = $this->request->getRequestParam("email");
        // $bucket = $this->request->getRequestParam("bucket");
        $label = $this->request->getRequestParam("label");
        $callback = $this->request->getRequestParam("callback");

        if (!$email) {
            return 400;
        }

        $bucket_names = Bucket::list($email, $label);

        if (count($bucket_names) === 0) {
            return 204;
        }

        $buckets = [];

        foreach ($bucket_names as $name) {

            $bucket = Bucket::get($name);

            // Create new auth key for each matching bucket
            $admin_key = bin2hex(random_bytes(18));

            $bucket_auth = new BucketAuth();
            $bucket_auth->list = true;
            $bucket_auth->read = true;
            $bucket_auth->create = true;
            $bucket_auth->edit = true;
            $bucket_auth->delete = true;
            $bucket_auth->admin = true;
            Auth::addBucketAuth($name, "bearer", $bucket_auth, secret: $admin_key);

            $buckets[] = [
                "name" => $name,
                "secret" => $admin_key,
                "last_modified" => $bucket->getLastModifiedDate()
            ];
        }

        Email::send(BucketRecover::class, $email, ["buckets" => $buckets, "callback" => $callback]);
    }
}
