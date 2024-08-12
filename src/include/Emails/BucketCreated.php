<?php

namespace KVStore\Emails;

class BucketCreated extends BaseEmail
{
    function subject($params): string
    {
        return 'Bucket Created: ' . $params['name'];
    }

    function body($params): string
    {
        return '<p>A new bucket has been created on kv.ijmacd.com.</p><p>The endpoint for this bucket is <a href="https://kv.ijmacd.com/' . $params['name'] . '">https://kv.ijmacd.com/' . $params['name'] . '</a>.</p><p>The admin secret for this bucket is: <code style="font-family: monospace; color: #666; border: 1px solid #CCC; border-radius: 1px; background: #EEE; padding: 1px">' . $params['secret'] . '</code></p>';
    }
}
