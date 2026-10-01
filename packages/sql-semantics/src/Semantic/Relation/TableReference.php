<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Relation;

use SqlSemantics\Core\Dialect;
use SqlSemantics\Semantic\Name;
use SqlSemantics\Semantic\QualifiedName;
use SqlSemantics\Semantic\Schema\Table;

/**
 * One use of a table; identity distinguishes occurrences in a self join.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT b.foo FROM bar b');
 *     $statement->tables[0]->visibleName()->value // => 'b'
 *
 * @visibility public
 */
final class TableReference
{
    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(
        public readonly Dialect $dialect,
        public readonly QualifiedName $name,
        public readonly ?Name $alias = null,
        public readonly ?Table $declaration = null,
        public readonly bool $catalogSupplied = false,
    ) {
        assert($declaration === null || ($catalogSupplied && $declaration->dialect === $dialect), 'A declaration must belong to the supplied catalog and dialect.');
        assert($declaration === null || $dialect->platform()->names()->relationEqual($declaration->name->name->value, $name->name->value), 'A relation must refer to its named declaration.');
        $namespace = $dialect->platform()->defaultSchema();
        assert($declaration === null || $dialect->platform()->names()->relationEqual($declaration->name->schema->value ?? $namespace, $name->schema->value ?? $namespace), 'A relation must refer to its declaration namespace.');
    }

    /**
     * Returns the alias when present, otherwise the relation name.
     */
    public function visibleName(): Name
    {
        return $this->alias ?? $this->name->name;
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        return $this->name->toString() . ($this->alias === null ? '' : ' AS ' . $this->alias->toString());
    }
}
