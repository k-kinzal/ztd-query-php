<?php

declare(strict_types=1);

namespace SqlCatalog\Facade;

use SqlCatalog\Core\Extension\ExtensionInterface;
use SqlCatalog\Core\Extension\ExtensionRegistry as Registry;
use SqlCatalog\Extension;

/**
 * Legacy registry composition with the package's default extension selection.
 *
 * @visibility root
 */
final class ExtensionRegistry extends Registry
{
    /**
     * @param list<ExtensionInterface> $extensions Extensions available to the run
     */
    public function __construct(array $extensions = [])
    {
        parent::__construct($extensions, ['pdo', 'mysqli']);
    }

    /**
     * The independent extensions shipped with the package.
     */
    public static function withBuiltins(): self
    {
        return new self([
            new Extension\Pdo\PdoExtension(),
            new Extension\Mysqli\MysqliExtension(),
            new Extension\Doctrine\DoctrineExtension(),
            new Extension\Laravel\LaravelExtension(Builtins::dialects()),
            new Extension\WordPress\WordPressExtension(),
        ]);
    }
}
