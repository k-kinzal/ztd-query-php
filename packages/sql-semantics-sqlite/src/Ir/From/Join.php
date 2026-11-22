<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\From;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One extension of the left-associated list of joined table occurrences.
 *
 * @visibility public
 * @example Adding the right input of a cross join
 *     $table = new \SqlSemantics\Platform\Sqlite\Ir\TableSource(new \SqlSemantics\Platform\Sqlite\Ir\Identifier('main'), new \SqlSemantics\Platform\Sqlite\Ir\Identifier('regions'), []);
 *     (new \SqlSemantics\Platform\Sqlite\Ir\From\Join(\SqlSemantics\Platform\Sqlite\Ir\From\JoinKind::Cross, $table))->source->table->value // => 'regions'
 */
final class Join
{
    use Snapshot;

    /**
     * Holds one right input and the ON predicate checked by the enclosing SELECT.
     */
    public function __construct(
        public readonly JoinKind $kind,
        public readonly TableSource|LocalSource $source,
        public readonly Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression|null $on = null,
    ) {
    }
}
