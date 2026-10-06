<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\OperandChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\PredicateTyping;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A pattern match: `a [NOT] LIKE p`, `ILIKE`, `SIMILAR TO`, with an optional `ESCAPE e`.
 *
 * Mirrors PostgreSQL's `A_Expr` node of kind `AEXPR_LIKE`, `AEXPR_ILIKE` or
 * `AEXPR_SIMILAR`; an ESCAPE clause becomes a call of `like_escape` or
 * `similar_to_escape` on the pattern.
 *
 * Rule: PG-PATTERN-MATCH-001. Facts: the type of the matching operator over
 * the operands (PG-OPERATOR-TYPING-001): `boolean` for string operands, and
 * for `bytea` with LIKE; NULL when an operand can be. The value must keep its
 * place before the keyword, the pattern after it, and the escape after
 * ESCAPE; with ESCAPE, the pattern may not end in a match without one, which
 * would take it (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/functions-matching.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a negated match with an escape character
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT 'a' NOT LIKE 'a!%' ESCAPE '!'");
 *     [$query->field(0)->expression->escape->value->value, $query->field(0)->type->descriptor->name()] // => ['!', 'boolean']
 */
final class PatternMatch implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $left The matched value
     * @param PatternOperator $operator The matching keyword
     * @param bool $negated Whether NOT is written
     * @param Scalar $right The pattern
     * @param Scalar|null $escape The escape character, or null when ESCAPE is not written
     */
    public function __construct(
        public readonly Scalar $left,
        public readonly PatternOperator $operator,
        public readonly bool $negated,
        public readonly Scalar $right,
        public readonly ?Scalar $escape = null,
    ) {
        $precedence = new Precedence();
        Check::input($precedence->before($left, Precedence::PATTERN), 'The matched value needs parentheses to keep its place.');
        Check::input($precedence->after($right, Precedence::PATTERN), 'The pattern needs parentheses to keep its place.');
        Check::input($escape === null || (!$precedence->takesEscape($right) && $precedence->after($escape, Precedence::PATTERN)), 'The pattern or the escape needs parentheses to keep its place.');
    }

    /**
     * Derives the operands and the type of the match.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $facts = [$derivation->scalar($this->left, $environment), $derivation->scalar($this->right, $environment)];
        if ($this->escape !== null) {
            $facts[] = $derivation->scalar($this->escape, $environment);
        }

        return new ScalarFact((new PredicateTyping())->pattern($derivation->context, $this->operator, $facts[0]->type, $facts[1]->type), (new OperandChecks())->nullability($facts));
    }

    /**
     * Writes the value, the keyword, the pattern and the escape.
     */
    public function render(Output $out): void
    {
        $out->node($this->left);
        if ($this->negated) {
            $out->keyword('NOT');
        }
        match ($this->operator) {
            PatternOperator::Like => $out->keyword('LIKE'),
            PatternOperator::ILike => $out->keyword('ILIKE'),
            PatternOperator::SimilarTo => $out->keyword('SIMILAR', 'TO'),
        };
        $out->node($this->right);
        if ($this->escape !== null) {
            $out->keyword('ESCAPE')->node($this->escape);
        }
    }
}
