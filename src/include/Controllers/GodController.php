<?php

namespace KVStore\Controllers;

use KVStore\Models\Auth;
use KVStore\Models\Bucket;
use KVStore\BucketAuth;

class GodController extends BaseController
{
    public function handle()
    {
        Auth::checkGodAuth();

        if (isset($_POST['method'])) {
            if ($_POST['method'] === "create_bucket" && isset($_POST['bucket_name'])) {
                return Bucket::create($_POST['bucket_name']) ? 200 : 400;
            }

            if ($_POST['method'] === "add_auth" && isset($_POST['bucket_name']) && isset($_POST['auth_type'])) {
                $identifier = isset($_POST['identifier']) ? $_POST['identifier'] : null;
                $secret = isset($_POST['secret']) ? $_POST['secret'] : null;

                $bucketAuth = new BucketAuth();
                $bucketAuth->list = isset($_POST['can_list']) && $_POST['can_list'] === "1";
                $bucketAuth->read = isset($_POST['can_read']) && $_POST['can_read'] === "1";
                $bucketAuth->create = isset($_POST['can_create']) && $_POST['can_create'] === "1";
                $bucketAuth->edit = isset($_POST['can_edit']) && $_POST['can_edit'] === "1";
                $bucketAuth->delete = isset($_POST['can_delete']) && $_POST['can_delete'] === "1";

                return Auth::addBucketAuth($_POST['bucket_name'], $_POST['auth_type'], $bucketAuth, $identifier, $secret);
            }

            if ($_POST['method'] === "remove_auth" && isset($_POST['bucket_name']) && isset($_POST['auth_type'])) {
                $identifier = isset($_POST['identifier']) ? $_POST['identifier'] : null;
                $secret = isset($_POST['secret']) ? $_POST['secret'] : null;

                Auth::removeBucketAuth($_POST['bucket_name'], $_POST['auth_type'], $identifier, $secret);
            }
        }

        return 400;
    }
}
