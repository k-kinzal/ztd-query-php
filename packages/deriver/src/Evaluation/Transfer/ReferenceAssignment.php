<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Transfer;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Value\Identity;
use Deriver\Value\Term;

/**
 * Enforces all live property declarations when their shared cell is written indirectly.
 * @visibility root
 */
final class ReferenceAssignment
{
    /**
     * @param Context $context Declaration and target semantics
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Intercepts a write through a typed reference without changing failed assignments.
     * @param CallableGraph $caller Executing graph
     * @param Instruction $instruction Write or increment
     * @param State $state Input path
     * @return list<State>|null Validated paths, or null when no property constrains the write
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state): ?array
    {
        $address = $state->addresses[$instruction->operands[0] ?? ''] ?? null;
        if ($address === null || !in_array($instruction->operation, ['write', 'increment'], true)) {
            return null;
        }
        $types = (new ReferenceConstraint())->find($state->memory, $address);
        if ($types === []) {
            return null;
        }
        $before = $state->memory->read($address);
        $value = $instruction->operation === 'write' ? $state->value($instruction->operands[1]) : (new MemoryStep($this->context))->incremented($before, $instruction);
        if ($value->kind === 'throwable') {
            $state->completion = new Completion('throw', $value);
            return [$state];
        }
        $check = $this->check($value, $types, $caller->strict);
        (new TypeBinding($this->context))->report($check, $instruction->source, $state);
        $exception = $state->fork();
        $exception->completion = new Completion('throw', $check->exception());
        if ($check->mustFail) {
            return [$exception];
        }
        $state->memory->write($address, $check->value);
        $state->registers[$instruction->result] = ($instruction->attributes['post'] ?? false) === true ? $before : $check->value;
        return $check->mayFail ? [$state, $exception] : [$state];
    }

    /**
     * Requires every declaration to accept the same coerced result.
     * @param Term $value Assigned value
     * @param list<string> $types Live property declaration types
     * @param bool $strict Calling-file scalar conversion mode
     * @return TypeCheck Common value or a type conflict
     */
    public function check(Term $value, array $types, bool $strict): TypeCheck
    {
        $result = null;
        $mayFail = false;
        $diagnostic = false;
        $coercion = null;
        $operation = 'object-string-coercion';
        foreach ($types as $type) {
            if ($type === 'mixed' || $type === '') {
                continue;
            }
            $check = (new TypeBinding($this->context))->check($value, $type, $strict);
            $diagnostic = $diagnostic || $check->diagnostic;
            if ($coercion === null && $check->coercion !== null) {
                $coercion = $check->coercion;
                $operation = $check->operation;
            }
            if ($check->mustFail) {
                return new TypeCheck($check->value, true, true, $diagnostic, $coercion, $operation);
            }
            if ($result !== null && (new Identity())->key($result) !== (new Identity())->key($check->value)) {
                return $value->isConcrete() ? new TypeCheck($value, true, true, $diagnostic, $coercion, $operation) : new TypeCheck(new Term('type-refinement', 'reference-constraints', [$value], ['type' => implode('&', $types)]), true, diagnostic: $diagnostic, coercion: $coercion, operation: $operation);
            }
            $result = $check->value;
            $mayFail = $mayFail || $check->mayFail;
        }
        return new TypeCheck($result ?? $value, $mayFail, diagnostic: $diagnostic, coercion: $coercion, operation: $operation);
    }
}
