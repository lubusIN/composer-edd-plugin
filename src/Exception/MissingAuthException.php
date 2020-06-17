<?php

namespace LubusIN\ComposerEddPlugin\Exception;

class MissingAuthException extends \Exception
{
    public function __construct($key)
    {
        parent::__construct(sprintf(
            '\'%1$s\' see https://github.com/lubusIN/composer-edd-plugin#step-3 for details.',
            $key
        ));
    }
}