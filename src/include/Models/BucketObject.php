<?php

namespace KVStore\Models;

use KVStore\DateTimeJSON;

class BucketObject
{
    var string $key;
    var mixed $value;
    var DateTimeJSON $created;

    function __toString()
    {
        $v = is_string($this->value) ? $this->value : json_encode($this->value);
        return "key: " . $this->key . "\nvalue: " . $v . "\ncreated: " . $this->created . "\n";
    }

    static function fromArray($array)
    {
        $object = new self();

        $object->key = $array["key"];
        $object->value = json_decode($array["value"]);
        $object->created = new DateTimeJSON($array["created_at"]);

        return $object;
    }
}
