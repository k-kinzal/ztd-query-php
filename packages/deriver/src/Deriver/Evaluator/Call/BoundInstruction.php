<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call;

use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Lowerer\Graph\Argument;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Literal;
use Deriver\Deriver\Sources\SourceRef;

/**
 * A call evaluator's instruction with a pre-bound symbolic value.
 * Source lowering only produces ordinary instructions with literal syntax.
 * @visibility root
 */
final class BoundInstruction extends Instruction
{
    /**
     * An evaluator-supplied value independent of source literal syntax.
     */
    public readonly ?Term $bound;

    /**
     * @param list<string> $operands Ordered register dependencies
     * @param list<Argument> $arguments Call arguments
     * @param array<string, scalar|null> $attributes Instruction facts
     */
    public function __construct(string $id, string $operation, SourceRef $source, string $result = '', array $operands = [], string $name = '', ?Term $constant = null, array $arguments = [], array $attributes = [])
    {
        $this->bound = $constant;
        parent::__construct($id, $operation, $source, $result, $operands, $name, $constant?->kind === TermKind::Constant ? Literal::constant($constant->literal) : null, $arguments, $attributes);
    }
}
