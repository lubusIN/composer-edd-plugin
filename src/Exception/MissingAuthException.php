<?php

declare(strict_types=1);

namespace LubusIN\ComposerEddPlugin\Exception;

use Exception;

class MissingAuthException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct(sprintf(
            '%1$s (see https://github.com/lubusIN/composer-edd-plugin for details)',
            $message
        ));
    }
}
