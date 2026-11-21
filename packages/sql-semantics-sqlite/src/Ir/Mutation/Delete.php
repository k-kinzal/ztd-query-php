<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * The removal of rows from one resolved SQLite base table.
 *
 * @visibility public
 * @example Constructing a delete of all rows
 *     $target = new \SqlSemantics\Platform\Sqlite\Ir\TableSource(new \SqlSemantics\Platform\Sqlite\Ir\Identifier('main'), new \SqlSemantics\Platform\Sqlite\Ir\Identifier('entries'), []);
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Mutation\Delete($target))->toString() // => 'DELETE FROM "main"."entries" AS "__ztd_source"'
 */
final class Delete implements SemanticsIR
{
    use Snapshot;

    /**
     * Checks the predicate against the single target occurrence.
     */
    public function __construct(
        public readonly TableSource $target,
        public readonly Arithmetic|BlobLiteral|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral|null $where = null,
    ) {
        (new ExpressionScope())->check([$target], $where === null ? [] : [[$where, 0, 1]]);
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Writes the resolved table and omission or value of the predicate.
     */
    public function toString(): string
    {
        $renderer = new SelectSql();
        $target = $renderer->source($this->target, 0);
        $where = $this->where === null ? '' : ' WHERE ' . $renderer->expression($this->where, $this->target);

        return 'DELETE FROM ' . $target . $where;
    }
}
