<?php

namespace LubusIN\ComposerEddPlugin\Exception;

class MissingEnvException extends \Exception
{
    public function __construct($key)
    {
        parent::__construct(sprintf(
            'Environment variable \'%1$s\' is not set.',
            $key
        ));
    }
}