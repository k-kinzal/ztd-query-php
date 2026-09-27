<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Transfer;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
use Deriver\Value\Term;

/**
 * Distinguishes stable explicit environment inputs from fresh nondeterministic events.
 * @visibility root
 */
final class ExternalTransfer
{
    /**
     * @param Context $context Explicit environment contract
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Creates input expressions and preserves target range-validation exceptions.
     * @param Instruction $instruction Registered intrinsic
     * @param State $state Input path
     * @param list<Term> $values Evaluated arguments
     * @return list<State> Valid input and exceptional paths
     */
    public function apply(Instruction $instruction, State $state, array $values): array
    {
        $name = $instruction->name;
        if (in_array($name, ['random_int', 'rand', 'mt_rand'], true)) {
            return $this->random($instruction, $state, $values);
        }
        $key = $this->environmentKey($name, $values);
        $stable = $name === 'getenv';
        $identity = $stable ? $key : $key . ':' . $state->memory->fresh('event');
        $type = $name === 'getenv' ? $this->environmentType($values) : ($name === 'microtime' ? $this->clockType($values) : 'int');
        $state->registers[$instruction->result] = $this->context->configuration->environment[$key] ?? new Term('external', $identity, $values, ['type' => $type, 'source' => $key, 'stability' => $stable ? 'environment' : 'evaluation']);
        return [$state];
    }

    /**
     * Retains a fresh bounded random event and every applicable target exception.
     * @param Instruction $instruction Random intrinsic
     * @param State $state Current path
     * @param list<Term> $values Bound range endpoints
     * @return list<State> Normal sample, range error, and entropy-source failure
     */
    public function random(Instruction $instruction, State $state, array $values): array
    {
        $error = $this->rangeError($values, $instruction->name);
        if ($error !== null) {
            $state->completion = new Completion('throw', new Term('throwable', $error));
            return [$state];
        }
        $min = $values[0] ?? new Term('omitted');
        $max = $values[1] ?? new Term('omitted');
        if ($min->kind === 'constant' && $max->kind === 'constant' && is_int($min->literal) && $min->literal === $max->literal) {
            $state->registers[$instruction->result] = Term::constant($min->literal, $min->isSecret() || $max->isSecret());
            return [$state];
        }
        $state->registers[$instruction->result] = new Term('external', $instruction->name . ':' . $state->memory->fresh('event'), $values, ['type' => 'int', 'source' => $instruction->name, 'stability' => 'evaluation']);
        $paths = [$state];
        if ($instruction->name !== 'rand' && ($min->kind !== 'constant' && $min->kind !== 'omitted' || $max->kind !== 'constant' && $max->kind !== 'omitted')) {
            $failure = $state->fork();
            $failure->completion = new Completion('throw', new Term('throwable', 'ValueError'));
            $paths[] = $failure;
        }
        if ($instruction->name === 'random_int') {
            $failure = $state->fork();
            $failure->completion = new Completion('throw', new Term('throwable', 'Random\\RandomException'));
            $paths[] = $failure;
        }
        return $paths;
    }

    /**
     * Separates complete environment capture and SAPI versus local lookups.
     * @param string $name Input function
     * @param list<Term> $values Bound arguments
     * @return string Explicit environment contract key
     */
    public function environmentKey(string $name, array $values): string
    {
        if ($name !== 'getenv' || (($values[0]->kind ?? 'constant') === 'constant' && ($values[0]->literal ?? null) === null)) {
            return $name;
        }
        if (($values[0]->kind ?? '') !== 'constant' || !is_string($values[0]->literal ?? null)) {
            return 'getenv:lookup';
        }
        $local = $values[1] ?? Term::constant(false);
        if ($local->kind !== 'constant' || !is_bool($local->literal)) {
            return 'getenv:lookup';
        }
        return ($local->literal ? 'env-local:' : 'env:') . $values[0]->literal;
    }

    /**
     * Preserves the nullable-name overload that returns the complete environment array.
     * @param list<Term> $values Bound arguments
     * @return string Conservative target result type
     */
    public function environmentType(array $values): string
    {
        $name = $values[0] ?? Term::constant(null);
        if ($name->kind === 'constant') {
            return $name->literal === null ? 'array' : 'string|false';
        }
        return ($name->attributes['type'] ?? '') === 'string' ? 'string|false' : 'array|string|false';
    }

    /**
     * Checks the two-bound random overload without substituting a missing argument.
     * @param list<Term> $values Bound random arguments
     * @param string $name Selected random function; rand accepts reversed bounds
     * @return string|null Target exception class
     */
    public function rangeError(array $values, string $name = 'mt_rand'): ?string
    {
        $min = $values[0] ?? new Term('omitted');
        $max = $values[1] ?? new Term('omitted');
        if (($min->kind === 'omitted') !== ($max->kind === 'omitted')) {
            return 'ArgumentCountError';
        }
        if ($name !== 'rand' && $min->kind === 'constant' && $max->kind === 'constant' && is_int($min->literal) && is_int($max->literal) && $min->literal > $max->literal) {
            return 'ValueError';
        }
        return null;
    }

    /**
     * Selects the microtime overload from its evaluated Boolean argument.
     * @param list<Term> $values Bound as_float argument
     * @return string Result type bound
     */
    public function clockType(array $values): string
    {
        $flag = $values[0] ?? Term::constant(false);
        return $flag->kind === 'constant' ? ($flag->literal === true ? 'float' : 'string') : 'string|float';
    }
}
