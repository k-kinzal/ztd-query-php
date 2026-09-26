<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Transfer;

use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\Context;
use Deriver\Internal\Solver\State;
use Deriver\Internal\Value\PhpSemantics;
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
     * @param CallableIR $callable Current callable
     * @param Instruction $instruction Pure instruction
     * @param State $state Input path
     * @return Term Evaluated value
     */
    public function evaluate(CallableIR $callable, Instruction $instruction, State $state): Term
    {
        $semantics = new PhpSemantics();
        $a = $state->value($instruction->operands[0] ?? '');
        $b = $state->value($instruction->operands[1] ?? '');
        return match ($instruction->operation) {
            'constant' => $instruction->constant ?? Term::constant(null),
            'copy' => $a,
            'binary' => $this->binary($instruction, $a, $b),
            'unary' => $semantics->unary($instruction->name, $a),
            'cast' => $semantics->cast($instruction->name, $a),
            'phi' => $state->previous === ($instruction->attributes['left'] ?? -1) ? $a : $b,
            'not-null' => $this->notNull($a),
            'constant-fetch' => $this->constant($instruction->name, $instruction),
            'magic-constant' => $this->magic($callable, $instruction),
            'array-read' => $this->arrayRead($a, $b, $state),
            'external' => $this->external($instruction, $state),
            'raise' => new Term('throwable', $instruction->name),
            default => $this->context->frontier('UNSUPPORTED_LANGUAGE_FEATURE', $instruction->source, $instruction->operation, [$a, $b]),
        };
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
     * @param CallableIR $callable Current callable
     * @param Instruction $instruction Magic constant
     * @return Term Constant value
     */
    public function magic(CallableIR $callable, Instruction $instruction): Term
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
        $key = (new PhpSemantics())->arrayKey($key);
        if ($array->kind === 'array' && $key->kind === 'throwable') {
            return $key;
        }
        if ($array->kind === 'array' && $key->kind === 'constant' && (is_int($key->literal) || is_string($key->literal))) {
            return $state->memory->dereference($array->operands[$key->literal] ?? (($array->attributes['open'] ?? false) === true ? Term::opaque('UNKNOWN_ARRAY_KEY') : new Term('uninitialized')));
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
        if ((new \Deriver\Internal\Value\Arithmetic())->warning($instruction->name, $left, $right)) {
            $this->context->frontier('PHP_WARNING', $instruction->source, 'implicit-integer-precision-loss');
        }
        return (new PhpSemantics())->binary($instruction->name, $left, $right);
    }
}
