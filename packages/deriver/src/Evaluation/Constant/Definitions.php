<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Constant;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
use Deriver\Value\Term;

/**
 * Captures runtime constant definitions without defining constants in the host process.
 * @visibility root
 */
final class Definitions
{
    /**
     * @param Context $context Captured constant world
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * @param Instruction $instruction Bound define intrinsic
     * @param State $state Shared runtime constant storage
     * @param list<Term> $values Name, value, and case flag
     * @return list<State> Definition result or an explicit unsupported definition
     */
    public function apply(Instruction $instruction, State $state, array $values): array
    {
        [$name, $value, $insensitive] = $values;
        if ($name->kind !== 'constant' || !is_string($name->literal) || $insensitive->kind !== 'constant') {
            $state->memory->cells['constant-definition:unknown'] = Term::opaque('DYNAMIC_CONSTANT_DEFINITION', dependencies: $values);
            $state->registers[$instruction->result] = $this->context->frontier('UNSUPPORTED_MODEL_CASE', $instruction->source, 'dynamic-constant-definition', $values, 'bool');
            return [$state];
        }
        $key = 'constant:' . $name->literal;
        if (str_contains($name->literal, '::')) {
            $state->registers[$instruction->result] = new Term('throwable', 'ValueError');
            return [$state];
        }
        if ($insensitive->literal === true) {
            $this->context->frontier('PHP_WARNING', $instruction->source, 'case-insensitive-constant');
        }
        $builtin = in_array($name->literal, ['PHP_INT_SIZE', 'PHP_INT_MAX', 'PHP_INT_MIN', 'PHP_VERSION_ID', 'SORT_REGULAR', 'COUNT_NORMAL', 'SORT_NUMERIC', 'COUNT_RECURSIVE', 'ARRAY_FILTER_USE_BOTH', 'SORT_STRING', 'ARRAY_FILTER_USE_KEY', 'SORT_FLAG_CASE'], true) || in_array(strtolower($name->literal), ['null', 'true', 'false'], true);
        $duplicate = $builtin || isset($state->memory->cells[$key]) || isset($this->context->configuration->environment[$key]) || $this->context->program->constant($name->literal) !== null;
        if ($duplicate) {
            $this->context->frontier('PHP_WARNING', $instruction->source, 'invalid-constant-definition');
            $state->registers[$instruction->result] = Term::constant(false);
        } elseif (isset($state->memory->cells['constant-definition:unknown'])) {
            $state->memory->cells[$key] = Term::opaque('DYNAMIC_CONSTANT_DEFINITION', dependencies: [$value, $state->memory->cells['constant-definition:unknown']]);
            $state->registers[$instruction->result] = $this->context->frontier('UNSUPPORTED_MODEL_CASE', $instruction->source, 'possible-constant-redefinition', $values, 'bool');
        } else {
            $state->memory->cells[$key] = $state->memory->materialize($value);
            $state->registers[$instruction->result] = Term::constant(true);
        }
        return [$state];
    }
}
