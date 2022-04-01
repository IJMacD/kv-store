<?php

class DateTimeJSON extends DateTime implements JsonSerializable {
    function jsonSerialize(): mixed
    {
        return $this->format("c");
    }

    function __toString()
    {
        return $this->format("c");
    }
}