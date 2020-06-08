<?php

namespace LubusIN\ComposerEddPlugin\Exception;

class MissingExtraException extends \Exception
{
    public function __construct($key)
    {
        parent::__construct(sprintf(
            'Package extra \'%1$s\' is not set.',
            $key
        ));
    }
}