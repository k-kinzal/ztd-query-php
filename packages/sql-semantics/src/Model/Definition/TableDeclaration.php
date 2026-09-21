<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Schema\TableDefinition;

/**
 * Executable expressions associated with their declared columns and CHECK constraints.
 *
 * @example Reading a declared default
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind('CREATE TABLE t(id INTEGER DEFAULT 3)');
 *     $statement->definitions[0]->defaults['id']->symbol // => '3'
 *
 * @visibility public
 */
final class TableDeclaration
{
    /**
     * @param TableDefinition $table Storage declaration
     * @param array<int|string, Expression> $defaults Defaults indexed by column name
     * @param array<int|string, Expression> $generated Generated values indexed by column name
     * @param array<int, Expression> $checks Conditions indexed by table constraint position
     */
    public function __construct(
        public readonly TableDefinition $table,
        public readonly array $defaults,
        public readonly array $generated,
        public readonly array $checks,
    ) {
        Collections::objects($defaults, Expression::class, false);
        Collections::objects($generated, Expression::class, false);
        Collections::objects($checks, Expression::class, false);
    }
}
