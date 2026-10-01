<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Statement;

use SqlSemantics\Semantic\Expression\ColumnReference;
use SqlSemantics\Semantic\Name;
use SqlSemantics\Semantic\Relation\TableReference;
use SqlSemantics\Semantic\Scope;

/**
 * An insertion destination and its ordered target-column references.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('INSERT INTO bar (foo) VALUES (1)');
 *     $statement->target->width // => 1
 *
 * @visibility public
 */
final class InsertTarget
{
    /**
     * @var list<ColumnReference>
     */
    public readonly array $columns;
    /**
     * The namespace in which the represented inputs are evaluated.
     */
    public readonly Scope $scope;
    /**
     * The required column count, or null when an absent catalog prevents determining it.
     */
    public readonly ?int $width;
    /**
     * Whether the insertion explicitly names its destination columns.
     */
    public readonly bool $explicitColumns;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly TableReference $table, Name ...$names)
    {
        assert($table->alias === null, 'This insertion target has no alias.');
        $this->scope = new Scope($table->dialect, $table);
        $this->explicitColumns = $names !== [];
        if ($names === [] && $table->declaration !== null) {
            $names = array_map(static fn ($column): Name => $column->name, $table->declaration->columns);
        }
        $this->columns = array_map(fn (Name $name): ColumnReference => $this->scope->column($name), array_values($names));
        $keys = array_map(fn (Name $name): string => $table->dialect->platform()->names()->key($name->value), array_values($names));
        assert(count(array_unique($keys)) === count($keys), 'An INSERT target cannot name a column twice.');
        $this->width = $this->explicitColumns || $table->declaration !== null ? count($this->columns) : null;
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        return $this->table->toString() . ($this->explicitColumns ? ' (' . implode(', ', array_map(static fn (ColumnReference $column): string => $column->name->toString(), $this->columns)) . ')' : '');
    }
}
