<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Query;

use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Relation\Scope;

/**
 * A relation constructed from explicit rows, without a table scan or execution simulation.
 * @visibility public
 * @example Constructing one result row
 *     $scope = new \SqlSemantics\Statement\Relation\Scope(new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main'))));
 *     (new \SqlSemantics\Statement\Query\Rows(new \SqlSemantics\Statement\Query\Row($scope, new \SqlSemantics\Statement\Expression\NullConstant())))->toString() // => 'VALUES (NULL)'
 */
final class Rows implements Operation
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
     * Row widths remain explicit even for grammar-valid requests with incompatible widths.
     */
    public function __construct(Row $first, Row ...$rest)
    {
        $this->scope = $first->scope;
        $this->rows = [$first, ...array_values($rest)];
        foreach ($this->rows as $row) {
            \SqlSemantics\Statement\Validation\Check::input($row->scope === $this->scope, 'All rows of one VALUES relation use the same expression scope.');
        }
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
