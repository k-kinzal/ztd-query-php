<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Argument\NativePragmaInteger;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\PragmaScope;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaNumber;

/**
 * Resolves cache operations and their native argument conventions.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CachePragmaLowering
{
    /**
     * Dispatches the three native cache operations.
     * @param 'cache_size'|'default_cache_size'|'cache_spill' $name The resolved native operation
     * @throws InvalidConstruction When the explicit qualifier cannot be resolved
     */
    public function lower(BoundStatement $operation, Pragma $statement, string $name): CacheSize|CacheSpill|DefaultCacheSize
    {
        return match ($name) {
            'cache_size' => $this->capacity($operation, $statement),
            'default_cache_size' => $this->persistent($operation, $statement),
            'cache_spill' => $this->spilling($operation, $statement),
        };
    }

    /**
     * Resolves the cache sizing operand in the schema's live unit convention.
     * @throws InvalidConstruction When the explicit qualifier cannot be resolved
     */
    public function capacity(BoundStatement $operation, Pragma $statement): CacheSize
    {
        $schema = (new PragmaScope())->resolve($operation->context, $statement->name->schema) ?? new Identifier('main');
        $value = $statement->value;
        if ($value === null) {
            return new CacheSize($schema);
        }
        $text = $value instanceof PragmaNumber ? $value->nativeText() : $value->value;

        return new CacheSize($schema, new CacheAmount((new NativePragmaInteger())->parse($text) ?? 0));
    }

    /**
     * Resolves the persistent request with the native saturating absolute conversion.
     * @throws InvalidConstruction When the explicit qualifier cannot be resolved
     */
    public function persistent(BoundStatement $operation, Pragma $statement): DefaultCacheSize
    {
        $schema = (new PragmaScope())->resolve($operation->context, $statement->name->schema) ?? new Identifier('main');
        $value = $statement->value;
        if ($value === null) {
            return new DefaultCacheSize($schema);
        }
        $text = $value instanceof PragmaNumber ? $value->nativeText() : $value->value;
        $amount = (new NativePragmaInteger())->parse($text) ?? 0;
        $pages = $amount === -2147483648 ? 2147483647 : abs($amount);

        return new DefaultCacheSize($schema, new DefaultCachePages($pages));
    }

    /**
     * Resolves a read or the paired connection and pager effects of a spill argument.
     * @throws InvalidConstruction When the explicit qualifier cannot be resolved
     */
    public function spilling(BoundStatement $operation, Pragma $statement): CacheSpill
    {
        $schema = (new PragmaScope())->resolve($operation->context, $statement->name->schema) ?? new Identifier('main');
        $value = $statement->value;
        if ($value === null) {
            return new CacheSpill($schema);
        }
        $text = $value instanceof PragmaNumber ? $value->nativeText() : $value->value;

        return new CacheSpill($schema, (new SpillArgument())->read($text));
    }
}
