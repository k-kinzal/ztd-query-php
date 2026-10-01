<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Schema;

use SqlSemantics\Core\Dialect;
use SqlSemantics\Semantic\QualifiedName;

/**
 * A closed table declaration whose columns retain object identity.
 * @example Reading semantic relationships
 *     $dialect = \SqlSemantics\Platform\Sqlite\Dialect::Sqlite;
 *     $column = new \SqlSemantics\Semantic\Schema\Column(new \SqlSemantics\Semantic\Name('foo'), new \SqlSemantics\Core\Type\TypeDescriptor($dialect, 'integer'));
 *     $table = new \SqlSemantics\Semantic\Schema\Table($dialect, new \SqlSemantics\Semantic\QualifiedName(new \SqlSemantics\Semantic\Name('bar')), $column);
 *     $table->column('foo') === $column // => true
 *
 * @visibility public
 */
final class Table
{
    /**
     * @var list<Column>
     */
    public readonly array $columns;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly Dialect $dialect, public readonly QualifiedName $name, Column ...$columns)
    {
        $seen = [];
        foreach ($columns as $column) {
            $key = $dialect->platform()->names()->key($column->name->value);
            assert(!isset($seen[$key]) && $column->type->dialect === $dialect, 'Table columns must have distinct names and the table dialect.');
            $seen[$key] = true;
        }
        $this->columns = array_values($columns);
    }

    /**
     * Finds the declared column using the dialect's identifier comparison.
     */
    public function column(string $name): ?Column
    {
        foreach ($this->columns as $column) {
            if ($this->dialect->platform()->names()->equal($column->name->value, $name)) {
                return $column;
            }
        }
        return null;
    }
}
