<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Model;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Schema\TableDefinition;

/**
 * One occurrence of a declared table in a query scope.
 *
 * @example Accept this semantic value in a database-independent consumer
 *     $consume = static fn (\SqlSemantics\Core\Model\TableUse $value): string => $value::class;
 *     $consume instanceof \Closure // => true
 *
 * @visibility public
 */
final class TableUse
{
    /**
     * @param string $id Query-local relation identity
     * @param string $scopeId Owning scope
     * @param TableDefinition $declaration Resolved table
     * @param string|null $alias Explicit alias, hiding the declaration name in this scope
     * @param Node $source Original table reference
     */
    public function __construct(
        public readonly string $id,
        public readonly string $scopeId,
        public readonly TableDefinition $declaration,
        public readonly ?string $alias,
        public readonly Node $source,
    ) {
    }
}
