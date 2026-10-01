<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Transfer;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
use Deriver\Value\Arithmetic;
use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Transfers pure instructions using the target PHP semantics.
 * @visibility root
 */
final class PureStep
{
    /**
     * @param Context $context Query context
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Evaluates an instruction that does not write application state.
     * @param CallableGraph $callable Current callable
     * @param Instruction $instruction Pure instruction
     * @param State $state Input path
     * @return Term Evaluated value
     */
    public function evaluate(CallableGraph $callable, Instruction $instruction, State $state): Term
    {
        $semantics = new Operations($this->context->configuration->target->floatPrecision);
        $a = $state->value($instruction->operands[0] ?? '');
        $b = $state->value($instruction->operands[1] ?? '');
        return match ($instruction->operation) {
            'constant' => $instruction->constant ?? Term::constant(null),
            'copy' => $a,
            'binary' => $this->binary($instruction, $a, $b),
            'unary' => $semantics->unary($instruction->name, $a),
            'cast' => $semantics->cast($instruction->name, $a),
            'phi' => $this->phi($instruction, $state),
            'not-null' => $this->notNull($a),
            'constant-fetch' => $this->constant($instruction->name, $instruction),
            'magic-constant' => $this->magic($callable, $instruction),
            'array-read' => $this->arrayRead($a, $b, $state),
            'external' => $this->external($instruction, $state),
            'evaluation-order' => Term::parameter($state->memory->fresh('evaluation-order'), 'bool'),
            'raise' => new Term('throwable', $instruction->name),
            default => $this->context->frontier('UNSUPPORTED_LANGUAGE_FEATURE', $instruction->source, $instruction->operation, [$a, $b]),
        };
    }

    /**
     * Selects a branch value while retaining confidentiality of its selecting condition.
     * @param Instruction $instruction Phi inputs and predecessor blocks
     * @param State $state Current registers and predecessor
     * @return Term Selected value
     */
    public function phi(Instruction $instruction, State $state): Term
    {
        $index = $state->previous === ($instruction->attributes['left'] ?? -1) ? 0 : 1;
        $value = $state->value($instruction->operands[$index] ?? '');
        $condition = $state->value($instruction->operands[2] ?? '');
        return $condition->isSecret() && !$value->secret ? new Term($value->kind, $value->literal, $value->operands, $value->attributes, true) : $value;
    }

    /**
     * Evaluates null/existence tests without conflating uninitialized with Bottom.
     * @param Term $value Input
     * @return Term Boolean predicate
     */
    public function notNull(Term $value): Term
    {
        if ($value->kind === 'uninitialized') {
            return Term::constant(false, $value->isSecret());
        }
        if ($value->kind === 'constant') {
            return Term::constant($value->literal !== null, $value->isSecret());
        }
        if (in_array($value->kind, ['array', 'object', 'closure', 'enum'], true)) {
            return Term::constant(true, $value->isSecret());
        }
        return new Term('binary', '!==', [$value, Term::constant(null)], ['type' => 'bool']);
    }

    /**
     * Resolves target constants rather than host environment constants.
     * @param string $name Constant name
     * @param Instruction $instruction Origin
     * @return Term Constant or boundary
     */
    public function constant(string $name, Instruction $instruction): Term
    {
        return match (strtolower($name)) {
            'null' => Term::constant(null), 'true' => Term::constant(true), 'false' => Term::constant(false),
            'php_int_size' => Term::constant(8), 'php_int_max' => Term::constant(9223372036854775807),
            'php_int_min' => Term::constant(-9223372036854775807 - 1),
            'php_version_id' => Term::constant(80300),
            'sort_regular', 'count_normal' => Term::constant(0),
            'sort_numeric', 'count_recursive', 'array_filter_use_both' => Term::constant(1),
            'sort_string', 'array_filter_use_key' => Term::constant(2),
            'sort_flag_case' => Term::constant(8),
            default => $this->context->configuration->environment['constant:' . $name] ?? $this->context->frontier('INCOMPLETE_SOURCE', $instruction->source, 'constant:' . $name),
        };
    }

    /**
     * Resolves source-based magic constants.
     * @param CallableGraph $callable Current callable
     * @param Instruction $instruction Magic constant
     * @return Term Constant value
     */
    public function magic(CallableGraph $callable, Instruction $instruction): Term
    {
        return Term::constant(match ($instruction->name) {
            '__LINE__' => $instruction->source->line,
            '__FILE__' => $instruction->source->path,
            '__DIR__' => dirname($instruction->source->path),
            '__CLASS__' => $callable->className,
            '__METHOD__', '__FUNCTION__' => $callable->symbol,
            default => '',
        });
    }

    /**
     * Reads an array element after normalizing its key.
     * @param Term $array Array value
     * @param Term $key Key value
     * @param State $state Reference memory
     * @return Term Selected value or symbolic lookup
     */
    public function arrayRead(Term $array, Term $key, State $state): Term
    {
        $key = (new Operations())->arrayKey($key);
        if ($array->kind === 'array' && $key->kind === 'throwable') {
            return $key;
        }
        if ($array->kind === 'array' && $key->kind === 'constant' && (is_int($key->literal) || is_string($key->literal))) {
            return $state->memory->element($array, $key->literal, $key->isSecret());
        }
        return new Term('array-read', operands: [$array, $key]);
    }

    /**
     * Creates a fresh nondeterministic event or an explicitly stable input.
     * @param Instruction $instruction Input declaration
     * @param State $state Current event sequence
     * @return Term External symbol or supplied environment value
     */
    public function external(Instruction $instruction, State $state): Term
    {
        $supplied = $this->context->configuration->environment[$instruction->name] ?? null;
        if ($supplied !== null) {
            return $supplied;
        }
        return new Term('external', $instruction->name . ':' . $state->memory->fresh('event'), attributes: ['type' => (string) ($instruction->attributes['type'] ?? 'mixed'), 'source' => $instruction->name, 'stability' => 'evaluation']);
    }

    /**
     * Applies a binary operator and records target diagnostics without host coercion warnings.
     * @param Instruction $instruction Operator and source location
     * @param Term $left Left value
     * @param Term $right Right value
     * @return Term Target value or throwable
     */
    public function binary(Instruction $instruction, Term $left, Term $right): Term
    {
        if ((new Arithmetic())->warning($instruction->name, $left, $right)) {
            $this->context->frontier('PHP_WARNING', $instruction->source, 'implicit-integer-precision-loss');
        }
        return (new Operations($this->context->configuration->target->floatPrecision))->binary($instruction->name, $left, $right);
    }
}
