<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Insertion;

use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Column;

/**
 * The destination relation and ordered column references of an insertion.
 * @visibility public
 * @example Describing explicitly named target columns without a declaration
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')), complete: false);
 *     $relation = new \SqlSemantics\Statement\Relation\TableReference($catalog, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('bar')));
 *     (new \SqlSemantics\Statement\Insertion\Target($relation, new \SqlSemantics\Statement\Identifier\Name('foo')))->toString() // => 'bar (foo)'
 */
final class Target
{
    /**
     * The target scope is distinct from the scope that produces input rows.
     */
    public readonly Scope $scope;

    /**
     * @var list<ColumnReference>|null Null means implicit target columns need an unambiguous declaration
     */
    public readonly ?array $columns;

    /**
     * Whether SQL explicitly supplies a destination column list.
     */
    public readonly bool $explicitColumns;

    /**
     * Explicit and implicit column mappings retain references to the actual declaration objects.
     */
    public function __construct(public readonly TableReference $table, Name ...$names)
    {
        $this->scope = new Scope($table->catalog, $table);
        $this->explicitColumns = $names !== [];
        if ($names === [] && count($table->declarations) !== 1) {
            $this->columns = null;
            return;
        }
        $names = $names === [] ? array_map(static fn (Column $column): Name => $column->name, $table->declarations[0]->columns) : $names;
        $this->columns = array_map(fn (Name $name): ColumnReference => new ColumnReference($this->scope, $name), array_values($names));
    }

    /**
     * Determines whether every supplied row fits the declared or explicitly named columns.
     */
    public function arity(int $first, int ...$rest): Arity
    {
        $widths = [$first, ...array_values($rest)];
        foreach ($widths as $width) {
            assert($width >= 0, 'An input row cannot have a negative width.');
        }
        if (count(array_unique($widths)) !== 1) {
            return Arity::Mismatch;
        }
        if ($this->columns === null) {
            return count($this->table->declarations) > 1 ? Arity::ConflictingDeclarations : ($this->table->catalog->complete ? Arity::MissingTable : Arity::MissingDeclaration);
        }
        return array_filter($widths, fn (int $width): bool => $width !== count($this->columns ?? [])) === [] ? Arity::Matching : Arity::Mismatch;
    }

    /**
     * Omits an implicit mapping and writes explicit destination column names in order.
     */
    public function toString(): string
    {
        return $this->table->toString() . (!$this->explicitColumns ? '' : ' (' . implode(', ', array_map(static fn (ColumnReference $column): string => $column->name->toString(), $this->columns ?? [])) . ')');
    }
}
