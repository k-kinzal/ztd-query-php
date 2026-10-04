<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Expression\Precedence;
use SqlSemantics\Platform\Sqlite\Rules\Expression\RowValueUse;
use SqlSemantics\Platform\Sqlite\Rules\Typing\Storages;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A pattern match: LIKE, GLOB, REGEXP or MATCH, optionally negated, optionally with an escape character.
 *
 * Rule: SQLITE-PATTERN-MATCH-001. LIKE and GLOB call the built-in functions
 * of those names: the result is INTEGER and NULL when an operand is NULL.
 * REGEXP and MATCH call the application-defined functions regexp() and
 * match(), which SQLite does not provide, so their result depends on the
 * undeclared routine. Every operand is a single value
 * (SQLITE-ROW-VALUE-USE-001). The operands must keep their place without
 * parentheses (SQLITE-PRECEDENCE-001); the pattern before ESCAPE may not end
 * in a pattern match without ESCAPE, which would take the ESCAPE.
 * Source: https://sqlite.org/lang_expr.html#the_like_glob_regexp_match_and_extract_operators.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a negated LIKE with an escape character
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("SELECT a NOT LIKE 'x!%' ESCAPE '!' FROM t");
 *     $match = $query->statement->columns[0]->expression;
 *     [$match->operator, $match->negated, $match->escape->value] // => [\SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternOperator::Like, true, '!']
 * @example Refusing a pattern that the rendered SQL would associate differently
 *     $or = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a OR b FROM t')->statement->columns[0]->expression;
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternMatch(\SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternOperator::Like, new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral(), $or) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class PatternMatch implements Scalar
{
    use Snapshot;

    /**
     * @param PatternOperator $operator The operator
     * @param Scalar $left The matched expression
     * @param Scalar $right The pattern
     * @param bool $negated Whether NOT is written before the operator
     * @param Scalar|null $escape The escape character expression
     */
    public function __construct(
        public readonly PatternOperator $operator,
        public readonly Scalar $left,
        public readonly Scalar $right,
        public readonly bool $negated = false,
        public readonly ?Scalar $escape = null,
    ) {
        $precedence = new Precedence();
        Check::input($precedence->closing($left) >= Precedence::EQUALITY, 'The matched operand needs parentheses to keep its place.');
        Check::input($precedence->opening($right) > Precedence::EQUALITY, 'The pattern needs parentheses to keep its place.');
        Check::input($escape === null || (!$precedence->takesEscape($right) && $precedence->opening($escape) > Precedence::EQUALITY), 'The pattern or the escape operand needs parentheses to keep its place.');
    }

    /**
     * Derives the operands and the result of the function the operator calls.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $left = $derivation->scalar($this->left, $environment);
        $right = $derivation->scalar($this->right, $environment);
        $nullability = $left->nullability->propagate($right->nullability);
        $types = [$left->type, $right->type];
        $rows = new RowValueUse();
        $rows->single($left, $derivation);
        $rows->single($right, $derivation);
        if ($this->escape !== null) {
            $escape = $derivation->scalar($this->escape, $environment);
            $nullability = $nullability->propagate($escape->nullability);
            $types[] = $escape->type;
            $rows->single($escape, $derivation);
        }
        if ($this->operator === PatternOperator::Regexp || $this->operator === PatternOperator::Match) {
            return new ScalarFact(new Dependent([new UndeclaredRoutine(new QualifiedName(new Name($this->operator === PatternOperator::Regexp ? 'regexp' : 'match')))]), Nullability::Dependent);
        }

        return new ScalarFact((new Storages())->strict(Storage::Integer, $types), $nullability);
    }

    /**
     * Writes the match.
     */
    public function render(Output $out): void
    {
        $out->node($this->left);
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword($this->operator->value)->node($this->right);
        if ($this->escape !== null) {
            $out->keyword('ESCAPE')->node($this->escape);
        }
    }
}
