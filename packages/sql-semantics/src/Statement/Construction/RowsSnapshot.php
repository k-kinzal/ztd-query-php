<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction;

use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Query\Row;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Validation\Check;

/**
 * Explicit VALUES operands derived in one fresh expression environment.
 * @visibility SqlSemantics
 */
final class RowsSnapshot
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * The new expression environment shared by these row positions.
     */
    public readonly Scope $scope;
    /**
     * @var non-empty-list<Row>
     */
    public readonly array $rows;

    /**
     * Row widths remain explicit; a semantic mismatch never discards a tuple.
     */
    public function __construct(Catalog|Scope|SqliteAliasScope $context, Query\RowsDefinition $input)
    {
        $catalog = $context instanceof Catalog ? $context : $context->catalog;
        Check::input($catalog->profile->grammar->database() === 'sqlite', 'This VALUES constructor requires the SQLite semantic profile.');
        $this->scope = new Scope($context);
        $expressions = new ExpressionConstruction();
        $this->rows = array_map(fn (Query\RowDefinition $row): Row => new Row($this->scope, ...array_map(fn (ScalarInput $item): ScalarExpression => $expressions->derive($item, $this->scope), $row->expressions)), $input->rows);
        Check::input((new \SqlSemantics\Statement\SemanticGraph())->containsOnlyValues($this), 'VALUES retains only closed immutable semantic values.');
    }
}
