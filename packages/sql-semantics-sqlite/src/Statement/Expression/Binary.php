<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;

/**
 * A binary operation over two operands, in the order written.
 *
 * Rule: SQLITE-BINARY-001. A comparison or logical operator yields INTEGER.
 * An arithmetic operator yields INTEGER or REAL depending on the values, so
 * both are kept as the known choice. The result can be NULL when an operand
 * can. Source: https://sqlite.org/lang_expr.html#operators_and_parse_affecting_attributes.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading both operands
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 + 2');
 *     [$query->statement->columns[0]->expression->left->digits, $query->statement->columns[0]->expression->right->digits] // => ['1', '2']
 */
final class Binary implements Scalar
{
    use Snapshot;

    /**
     * @param BinaryOperator $operator The operator
     * @param Scalar $left The left operand
     * @param Scalar $right The right operand
     */
    public function __construct(public readonly BinaryOperator $operator, public readonly Scalar $left, public readonly Scalar $right)
    {
    }

    /**
     * Derives both operands and combines their facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $left = $derivation->scalar($this->left, $environment);
        $right = $derivation->scalar($this->right, $environment);

        return new ScalarFact(
            $this->operator->logical() ? new Known(Storage::Integer) : new Choice([Storage::Integer, Storage::Real]),
            $left->nullability->propagate($right->nullability),
        );
    }

    /**
     * Writes the operands around the operator.
     */
    public function render(Output $out): void
    {
        $out->node($this->left);
        if ($this->operator === BinaryOperator::And || $this->operator === BinaryOperator::Or) {
            $out->keyword($this->operator->value);
        } else {
            $out->symbol($this->operator->value);
        }
        $out->node($this->right);
    }
}
