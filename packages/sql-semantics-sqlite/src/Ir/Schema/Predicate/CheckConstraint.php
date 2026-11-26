<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A check condition on rows of the owning table.
 *
 * @visibility public
 * @example Naming a table condition
 *     $label = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('positive');
 *     $condition = new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('1');
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\CheckConstraint($label, $condition))->label->value // => 'positive'
 */
final class CheckConstraint
{
    use Snapshot;

    /**
     * Checks the definition expression, including any folded marker registrations.
     * @param int|null $column The owning declaration slot for an inline check
     */
    public function __construct(
        public readonly Identifier|ImplicitCheckLabel $label,
        public readonly Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression $condition,
        public readonly ?int $column = null,
    ) {
        Check::input($column === null || ($column >= 0 && $column < 2000), 'An inline CHECK must have a column slot within the profile.');
        if ($label instanceof ImplicitCheckLabel) {
            Check::input(
                $condition instanceof Comparison && $condition->left instanceof ColumnReference
                    && $condition->right instanceof IntegerLiteral && !str_starts_with($condition->right->decimal, '-')
                    && $condition->operator === $label->operation(),
                'An implicit CHECK label must present its resolved column comparison and nonnegative literal.',
            );
        }
        (new ExpressionScope())->check([2000], [[$condition, 0, 1]], false);
    }

    /**
     * Relates the displayed spelling to the bound slot and naming state in the owning statement.
     *
     * @param non-empty-list<Identifier> $heading
     */
    public function checkPresentation(array $heading, bool $inheritsName = false): void
    {
        $label = $this->label;
        if (!$label instanceof ImplicitCheckLabel) {
            return;
        }
        Check::input(!$inheritsName, 'An implicit CHECK cannot follow a named CHECK in the same inline declaration.');
        $condition = $this->condition;
        Check::invariant($condition instanceof Comparison && $condition->left instanceof ColumnReference, 'The implicit CHECK has a resolved comparison.');
        $declared = $heading[$condition->left->slot] ?? null;
        Check::input($declared !== null && $declared->key() === $label->columnName->key(), 'The implicit CHECK spelling must identify the resolved column.');
    }
}
