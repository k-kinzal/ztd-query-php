<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Expression;

use Deriver\Deriver\Evaluator\Call\BoundInstruction;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Literal;
use Deriver\Deriver\Lowerer\Graph\LiteralKind;

/**
 * Interprets source literals at the boundary of evaluation.
 * @visibility parent
 * @visibility Deriver\Deriver\Execution
 */
final class LiteralEvaluator
{
    /**
     * Evaluates literal syntax without modifying the lowerer's representation.
     */
    public function evaluate(?Literal $literal): Term
    {
        return match ($literal?->kind) {
            null, LiteralKind::Constant => Term::constant($literal?->literal),
            LiteralKind::Array => new Term(TermKind::Array, operands: array_map($this->evaluate(...), $literal->operands), attributes: $literal->attributes),
            LiteralKind::Enum => new Term(TermKind::Enum, $literal->literal, array_map($this->evaluate(...), $literal->operands), $literal->attributes),
            LiteralKind::Unresolved => Term::opaque((string) $literal->literal),
        };
    }

    /**
     * Call evaluators may supply an already evaluated value in their own instruction.
     */
    public function instruction(Instruction $instruction): Term
    {
        return $instruction instanceof BoundInstruction && $instruction->bound !== null ? $instruction->bound : $this->evaluate($instruction->constant);
    }
}
