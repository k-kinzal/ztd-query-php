<?php

declare(strict_types=1);

namespace SqlCatalog\Facade;

use SqlCatalog\Core\Sql\Dialects;
use SqlCatalog\Extension\Laravel\LaravelExtension as Extension;

/**
 * Composes the Laravel extension with the built-in SQL policies by default.
 *
 * @visibility root
 */
final class LaravelExtension extends Extension
{
    /**
     * Uses application policies when supplied.
     */
    public function __construct(?Dialects $dialects = null)
    {
        parent::__construct($dialects ?? Builtins::dialects());
    }
}
