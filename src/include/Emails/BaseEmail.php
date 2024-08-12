<?php

namespace KVStore\Emails;

abstract class BaseEmail
{
    abstract function subject($params): string;

    abstract function body($params): string;
}
