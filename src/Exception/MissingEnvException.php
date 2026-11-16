<?php

declare(strict_types=1);

namespace LubusIN\ComposerEddPlugin\Exception;

use Exception;

class MissingEnvException extends Exception
{
    public function __construct(string $key)
    {
        parent::__construct(sprintf(
            'Environment variable \'%1$s\' is not set.',
            $key
        ));
    }
}
