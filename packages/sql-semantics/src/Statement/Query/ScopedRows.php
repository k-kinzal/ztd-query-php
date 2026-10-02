<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Query;

use SqlSemantics\Statement\Relation\Scope;

/**
 * Correlated VALUES operands at one lexical use site, separate from a statement root.
 * @visibility public
 * @example Distinguishing correlated rows from a statement root
 *     is_a(\SqlSemantics\Statement\Query\ScopedRows::class, \SqlSemantics\Statement\Operation::class, true) // => false
 */
final class ScopedRows
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var non-empty-list<Row>
     */
    public readonly array $rows;

    /**
     * The single expression scope shared by the row constructor.
     */
    public readonly Scope $scope;

    /**
     * Creates a new correlated body in its explicit lexical environment, never from bound row parts.
     */
    public function __construct(Scope|\SqlSemantics\Statement\Relation\SqliteAliasScope $context, \SqlSemantics\Statement\Construction\Query\RowsDefinition $definition)
    {
        $snapshot = new \SqlSemantics\Statement\Construction\RowsSnapshot($context, $definition);
        $this->scope = $snapshot->scope;
        $this->rows = $snapshot->rows;
    }

    /**
     * Reads the exact declaration snapshot of this row-producing request.
     */
    public function context(): \SqlSemantics\Statement\Schema\Catalog
    {
        return $this->scope->catalog;
    }

    /**
     * Reads the fixed language profile used by the row expressions.
     */
    public function profile(): \SqlSemantics\Statement\Contract\LanguageProfile
    {
        return $this->scope->catalog->profile;
    }

    /**
     * Reports the width of each row without discarding incompatible input.
     * @return non-empty-list<int>
     */
    public function widths(): array
    {
        return array_map(static fn (Row $row): int => count($row->expressions), $this->rows);
    }

    /**
     * Reconstructs the relation from its rows and expressions.
     */
    public function toString(): string
    {
        return 'VALUES ' . implode(', ', array_map(static fn (Row $row): string => $row->toString(), $this->rows));
    }
}
