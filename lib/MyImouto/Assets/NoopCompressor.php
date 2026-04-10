<?php

namespace MyImouto\Assets;

class NoopCompressor
{
    public static function run($contents)
    {
        return (string) $contents;
    }
}
