<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Model;

use SqlCatalog\Extension\ExtensionInterface;

/**
 * An optional extension capability for source-level call and statement models.
 *
 * @visibility public
 *
 * @example Registering an extension with optional source models
 *     $extension = new class implements \SqlCatalog\Extension\Model\ModelProviderInterface {
 *         public function name(): string { return 'example'; }
 *         public function description(): string { return 'Application SQL functions'; }
 *         public function sinks(): array { return []; }
 *         public function globals(): array { return []; }
 *         public function models(\SqlCatalog\Extension\Model\ModelContext $context): \SqlCatalog\Extension\Model\ModelSet {
 *             return new \SqlCatalog\Extension\Model\ModelSet();
 *         }
 *     };
 *     $registry = new \SqlCatalog\Extension\ExtensionRegistry([$extension]);
 *     count($registry->modelProvidersOf(['example'])) // => 1
 */
interface ModelProviderInterface extends ExtensionInterface
{
    /**
     * Creates models for one analysis, without retaining state between runs.
     */
    public function models(ModelContext $context): ModelSet;
}
