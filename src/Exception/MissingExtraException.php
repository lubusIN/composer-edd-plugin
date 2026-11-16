<?php

declare(strict_types=1);

namespace LubusIN\ComposerEddPlugin\Exception;

use Exception;

class MissingExtraException extends Exception
{
    public function __construct(string $key)
    {
        parent::__construct(sprintf(
            'Package extra \'%1$s\' is not set.',
            $key
        ));
    }
}
