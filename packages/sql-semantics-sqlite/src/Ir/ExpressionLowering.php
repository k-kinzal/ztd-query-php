<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Ir\Expression\BinaryOperatorLowering;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseBranch;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastTarget;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastTypeName;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\CoalesceLowering;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryKind;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\NumericLiteralLowering;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembershipLowering;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipItem;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternKind;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Model\Contract\TypeNameAffinity;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Statement\Expression\BindParameter;
use SqlSemantics\Platform\Sqlite\Statement\Expression\CaseExpression as ParsedCase;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Cast as ParsedCast;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate as ParsedCollate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\BlobLiteral as ParsedBlob;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral as ParsedHex;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral as ParsedInteger;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral as ParsedReal;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral as ParsedText;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Between as ParsedBetween;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTest;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTestForm;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternMatch as ParsedPattern;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ParameterPrefix;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InList as ParsedMembership;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;
use SqlSemantics\Platform\Sqlite\Statement\Schema\LiteralColumn;

/**
 * Resolves the closed scalar expressions used by queries and writes.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ExpressionLowering
{
    /**
     * Retains SQLite's planner-sensitive expression names while compiling an index.
     */
    public function __construct(private readonly bool $indexSyntax = false)
    {
    }

    /**
     * Converts a scalar expression and resolves its column references.
     *
     * @throws InvalidConstruction When the expression is unsupported or a reference is unresolved
     */
    public function lower(Scalar $expression, SourceBinding $binding): Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }

        if ($expression instanceof Binary) {
            return $this->binary($expression, $binding);
        }
        if ($expression instanceof Unary) {
            return $this->prefix($expression, $binding);
        }

        return match (true) {
            $expression instanceof NullTest => $this->nullTest($expression, $binding),
            $expression instanceof ParsedBetween => new BetweenTest(
                $this->lower($expression->operand, $binding),
                $this->lower($expression->low, $binding),
                $this->lower($expression->high, $binding),
                $expression->negated,
            ),
            $expression instanceof ParsedMembership => $this->membership($expression, $binding),
            $expression instanceof ParsedPattern => $this->pattern($expression, $binding),
            $expression instanceof ParsedCollate => $this->collated($expression, $binding),
            $expression instanceof ParsedCase, $expression instanceof FunctionCall => $this->conditional($expression, $binding),
            $expression instanceof ParsedCast => $this->cast($expression, $binding),
            $expression instanceof ParsedInteger, $expression instanceof ParsedHex, $expression instanceof ParsedReal => (new NumericLiteralLowering())->lower($expression, false),
            $expression instanceof ParsedText => new TextLiteral($expression->value),
            $expression instanceof ParsedBlob => new BlobLiteral($expression->hex),
            $expression instanceof ParsedNull => new NullLiteral(),
            $expression instanceof BindParameter && $expression->prefix === ParameterPrefix::Question => new Parameter($expression->label),
            $expression instanceof TruthWord => $this->truthWord($expression, $binding),
            $expression instanceof ColumnUse, $expression instanceof DoubleQuotedWord, $expression instanceof LiteralColumn => $binding->scalarValue($expression),
            default => throw new InvalidConstruction('This expression has no complete SQLite scalar representation.'),
        };
    }

    /**
     * Resolves conditional evaluation over ordered branches or builtin arguments.
     * @throws InvalidConstruction When the conditional operation or its operands have no checked representation
     */
    public function conditional(ParsedCase|FunctionCall $expression, SourceBinding $binding): CaseExpression|Coalesce
    {
        return $expression instanceof ParsedCase
            ? $this->choice($expression, $binding)
            : (new CoalesceLowering())->lower($expression, $binding, $this, $this->indexSyntax);
    }

    /**
     * Resolves the infix syntax to the fixed built-in pattern operation.
     * @throws InvalidConstruction When a pattern routine has no resolved role
     */
    public function pattern(ParsedPattern $expression, SourceBinding $binding): PatternTest
    {
        $kind = match ($expression->operator) {
            PatternOperator::Like => PatternKind::AsciiLike,
            PatternOperator::Glob => PatternKind::Glob,
            PatternOperator::Regexp, PatternOperator::Match => throw new InvalidConstruction('This pattern routine has no resolved SQLite profile.'),
        };
        $subject = $this->lower($expression->left, $binding);
        $pattern = $this->lower($expression->right, $binding);
        $escape = $expression->escape === null ? null : $this->lower($expression->escape, $binding);

        return new PatternTest($kind, $subject, $pattern, $expression->negated, $escape);
    }

    /**
     * Resolves a postfix NULL test to a null-safe comparison role.
     * @throws InvalidConstruction When the tested operand cannot be resolved
     */
    public function nullTest(NullTest $expression, SourceBinding $binding): Comparison
    {
        $operator = match ($expression->form) {
            NullTestForm::IsNull => ComparisonOperator::Is,
            NullTestForm::NotNull, NullTestForm::NotNullWords => ComparisonOperator::IsNot,
        };

        return new Comparison($operator, $this->lower($expression->operand, $binding), new NullLiteral());
    }

    /**
     * Resolves the explicit builtin comparison sequence for a scalar.
     * @throws InvalidConstruction When the sequence or operand is unsupported
     */
    public function collated(ParsedCollate $expression, SourceBinding $binding): CollatedExpression
    {
        $collation = Collation::tryFrom(strtoupper($expression->collation->value));
        Check::input($collation !== null, 'An explicit collation requires a known SQLite builtin.');

        return new CollatedExpression($this->lower($expression->operand, $binding), $collation);
    }

    /**
     * Resolves the tested value followed by every ordered list member.
     * @throws InvalidConstruction When a member or tested value is unsupported or unresolved
     */
    public function membership(ParsedMembership $expression, SourceBinding $binding): FoldedMembership|MembershipTest
    {
        if ($expression->items === []) {
            return (new FoldedMembershipLowering())->lower($expression->operand, $expression->negated);
        }
        $operand = $this->lower($expression->operand, $binding);
        $members = [];
        foreach ($expression->items as $item) {
            $members[] = new MembershipItem($this->lower($item, $binding));
        }

        return new MembershipTest($operand, $members, $expression->negated);
    }

    /**
     * Selects the ordered operation and recognizes the native truth-test operand.
     * @throws InvalidConstruction When an operation or binding has no checked representation
     */
    public function binary(Binary $expression, SourceBinding $binding): Arithmetic|Comparison|Concatenation|Logical|TruthTest
    {
        if ($expression->operator === BinaryOperator::Concat) {
            $left = $this->lower($expression->left, $binding);
            $right = $this->lower($expression->right, $binding);

            return new Concatenation($left, $right);
        }
        $operator = (new BinaryOperatorLowering())->lower($expression->operator);
        $left = $this->lower($expression->left, $binding);
        $writtenRight = $expression->right;
        while ($writtenRight instanceof Grouped) {
            $writtenRight = $writtenRight->operand;
        }
        if (($operator === ComparisonOperator::Is || $operator === ComparisonOperator::IsNot)
            && $writtenRight instanceof TruthWord && ($desired = $binding->constantTruth($writtenRight)) !== null) {
            return new TruthTest($left, $desired, $operator === ComparisonOperator::IsNot);
        }
        $right = $this->lower($writtenRight, $binding);

        return match (true) {
            $operator instanceof ArithmeticOperator => new Arithmetic($operator, $left, $right),
            $operator instanceof ComparisonOperator => new Comparison($operator, $left, $right),
            $operator instanceof LogicalOperator => new Logical($operator, $left, $right),
        };
    }

    /**
     * Resolves a truth word to its column or integer role.
     * @throws InvalidConstruction When the written name has no checked column binding
     */
    public function truthWord(TruthWord $expression, SourceBinding $binding): Coalesce|ColumnReference|IntegerLiteral
    {
        $truth = $binding->constantTruth($expression);

        return $truth === null ? $binding->scalarValue($expression) : new IntegerLiteral($truth ? '1' : '0');
    }

    /**
     * Selects the literal or computed role of a prefix operation.
     * @throws InvalidConstruction When a signed literal or resolved operand is invalid
     */
    public function prefix(Unary $expression, SourceBinding $binding): HexIntegerLiteral|IntegerLiteral|RealLiteral|UnaryExpression
    {
        $literal = (new NumericLiteralLowering())->signed($expression);
        if ($literal !== null) {
            return $literal;
        }
        $operand = $this->lower($expression->operand, $binding);

        return match ($expression->operator) {
            UnaryOperator::Plus => new UnaryExpression(UnaryKind::Plus, $operand),
            UnaryOperator::Not => new UnaryExpression(UnaryKind::Not, $operand),
            UnaryOperator::BitNot => new UnaryExpression(UnaryKind::Complement, $operand),
            UnaryOperator::Minus => new UnaryExpression(UnaryKind::Minus, $operand),
        };
    }

    /**
     * Maps each checked conditional operand in its written order.
     *
     * @throws InvalidConstruction When a conditional operand is unsupported or a reference is unresolved
     */
    public function choice(ParsedCase $expression, SourceBinding $binding): CaseExpression
    {
        $branches = [];
        foreach ($expression->branches as $branch) {
            $branches[] = new CaseBranch($this->lower($branch->when, $binding), $this->lower($branch->then, $binding));
        }

        return new CaseExpression(
            $expression->base === null ? null : $this->lower($expression->base, $binding),
            $branches,
            $expression->otherwise === null ? null : $this->lower($expression->otherwise, $binding),
        );
    }

    /**
     * Resolves the written type name to the effective SQLite cast affinity.
     *
     * @throws InvalidConstruction When the operand has no complete scalar representation
     */
    public function cast(ParsedCast $expression, SourceBinding $binding): CastExpression
    {
        $native = $expression->nativeType();
        $target = CastTarget::from((new TypeNameAffinity())->conversion($native));
        $name = $this->indexSyntax && $native !== $target->value ? new CastTypeName($native) : null;

        return new CastExpression($target, $this->lower($expression->operand, $binding), $name);
    }
}
