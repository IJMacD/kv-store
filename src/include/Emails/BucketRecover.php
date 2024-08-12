<?php

namespace KVStore\Emails;

class BucketRecover extends BaseEmail
{
    function subject($params): string
    {
        return 'Bucket Recovery';
    }

    function body($params): string
    {
        $callback_domain = $params["callback"] ? parse_url($params["callback"], PHP_URL_HOST) : false;

        $html = '<p>Recovery has been requested for the following buckets belonging to you on kv.ijmacd.com.</p>';

        if ($callback_domain) {
            $html .= '<p>Clicking the links below will give ' . $callback_domain . ' access to these buckets.</p>';
        }

        $html .= '<ul>';

        foreach ($params['buckets'] as $bucket) {
            $html .= '<li>Bucket: ' . $bucket['name']  . ' (Last Modified: ' . $bucket['last_modified'] . ')' . '<br/>'
                . 'Secret: <code style="font-family: monospace; color: #666; border: 1px solid #CCC; border-radius: 1px; background: #EEE; padding: 1px">' . $bucket['secret'] . '</code><br/>';

            if ($callback_domain) {
                $url = $bucket['callback'] . '#bucket=' . $bucket['name'] . '&secret=' . $bucket['secret'];
                $html .=  '<a href="' . $url . '">' . $url . '</a>';
            }

            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
