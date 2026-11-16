<?php

declare(strict_types=1);

namespace LubusIN\ComposerEddPlugin\Exception;

use Exception;
use Throwable;

class MissingAuthException extends Exception
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct(sprintf(
            '%1$s (see https://github.com/lubusIN/composer-edd-plugin for details)',
            $message
        ), 0, $previous);
    }
}
