<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
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
     * @var list<ReturnedColumn> The ordered output fields
     */
    public readonly array $returning;

    /**
     * Checks the predicate and return fields against the single target occurrence.
     *
     * @param list<ReturnedColumn> $returning The requested output fields in order
     */
    public function __construct(
        public readonly TableSource $target,
        public readonly Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression|null $where = null,
        array $returning = [],
    ) {
        Check::input($target->kind === ExternalRelationKind::BaseTable, 'A mutation target must be a base table.');
        $checked = Check::listOf($returning, ReturnedColumn::class, 'A DELETE needs checked RETURNING fields.');
        Check::input(count($checked) <= 2000, 'A DELETE can return at most 2000 fields in this profile.');
        foreach ($checked as $column) {
            Check::input(isset($target->columns[$column->reference->slot]), 'A returned slot must belong to the DELETE target.');
        }
        (new ExpressionScope())->check([count($target->columns)], $where === null ? [] : [[$where, 0, 1]]);
        $this->returning = $checked;
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

        $returning = (new ReturningSql())->clause($this->target, $this->returning);

        return 'DELETE FROM ' . $target . $where . $returning;
    }
}
