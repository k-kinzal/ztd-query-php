<?php

declare(strict_types=1);

namespace Deriver\Constraint;

use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
use Deriver\Model\Provider\RefinementModel;
use Deriver\Value\Identity;
use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Preserves shared predicates and checks supported numeric interval contradictions.
 * @visibility root
 */
final class Constraints
{
    /**
     * @param Context|null $context Optional trusted refinement registry
     */
    public function __construct(public readonly ?Context $context = null)
    {
    }

    /**
     * Narrows a forked path under one branch predicate.
     * @param State $state Forked execution state
     * @param Term $predicate Branch condition
     * @param bool $truth Desired condition truth
     * @return bool Whether the supported constraints admit the path
     */
    public function assume(State $state, Term $predicate, bool $truth): bool
    {
        $known = (new Operations())->truth($predicate);
        if ($known !== null) {
            return $known === $truth;
        }
        if ($predicate->kind === 'unary' && $predicate->literal === '!' && isset($predicate->operands[0])) {
            return $this->assume($state, $predicate->operands[0], !$truth);
        }
        $key = (new Identity())->key($predicate);
        if (isset($state->guard[$key]) && $state->guard[$key] !== $truth) {
            return false;
        }
        $state->guard[$key] = $truth;
        return $this->comparison($state, $predicate, $truth) && $this->refine($state, $predicate, $truth);
    }

    /**
     * Adds bounds from comparisons against known numeric constants.
     * @param State $state Path state
     * @param Term $predicate Predicate expression
     * @param bool $truth Branch polarity
     * @return bool Whether bounds remain consistent
     */
    public function comparison(State $state, Term $predicate, bool $truth): bool
    {
        if ($predicate->kind !== 'binary' || !isset($predicate->operands[0], $predicate->operands[1]) || !is_string($predicate->literal)) {
            return true;
        }
        [$left, $right] = [$predicate->operands[0], $predicate->operands[1]];
        $operator = $predicate->literal;
        if ($left->kind === 'constant' && $right->kind !== 'constant') {
            [$left, $right] = [$right, $left];
            $operator = ['<' => '>', '<=' => '>=', '>' => '<', '>=' => '<='][$operator] ?? $operator;
        }
        if ($right->kind !== 'constant' || (!is_int($right->literal) && !is_float($right->literal)) || ($left->attributes['type'] ?? '') !== 'int') {
            return true;
        }
        if (!$truth) {
            $operator = ['<' => '>=', '<=' => '>', '>' => '<=', '>=' => '<', '===' => '!==', '!==' => '==='][$operator] ?? '';
        }
        return $this->bounds($state, $left, $right, $operator);
    }
    /**
     * Applies additional guaranteed predicates through the core constraint rules.
     * @param State $state Conditional execution state
     * @param Term $predicate Original predicate
     * @param bool $truth Branch polarity
     * @return bool Whether all applicable implications admit this path
     */
    public function refine(State $state, Term $predicate, bool $truth): bool
    {
        foreach ($this->context?->configuration->providers ?? [] as $provider) {
            if (!$provider instanceof RefinementModel) {
                continue;
            }
            $implied = $provider->refine($predicate, $truth);
            if ($implied !== null && !(new self())->assume($state, $implied, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Updates integer bounds after normalizing the comparison orientation.
     * @param State $state Current path
     * @param Term $left Symbolic integer
     * @param Term $right Numeric constant
     * @param string $operator Normalized comparison
     * @return bool Whether the interval remains inhabited
     */
    public function bounds(State $state, Term $left, Term $right, string $operator): bool
    {
        if (!is_int($right->literal) && !is_float($right->literal)) {
            return true;
        }
        $key = (new Identity())->key($left);
        $bounds = $state->constraints[$key] ?? ['min' => null, 'max' => null, 'equal' => null, 'excluded' => []];
        $number = $right->literal;
        if (!$this->ordered($bounds, $number, $operator)) {
            return false;
        }
        if ($operator === '===') {
            if (!is_int($number) || in_array($number, array_column($bounds['excluded'], 'literal'), true)) {
                return false;
            }
            $bounds['min'] = $bounds['min'] === null ? $number : max($number, $bounds['min']);
            $bounds['max'] = $bounds['max'] === null ? $number : min($number, $bounds['max']);
            $bounds['equal'] = $right;
        }
        if ($operator === '!==' && is_int($number)) {
            $bounds['excluded'][] = $right;
            if ($bounds['equal']?->literal === $number) {
                return false;
            }
        }
        $state->constraints[$key] = $bounds;
        return $bounds['min'] === null || $bounds['max'] === null || $bounds['min'] <= $bounds['max'];
    }

    /**
     * Updates ordered integer bounds without converting integer endpoints to floats.
     * @param array{min: int|float|null, max: int|float|null, equal: Term|null, excluded: list<Term>} $bounds Current interval
     * @param int|float $number Compared scalar
     * @param string $operator Normalized operator
     * @return bool Whether the bound permits a target integer
     */
    public function ordered(array &$bounds, int|float $number, string $operator): bool
    {
        if (($operator === '>' && $number === 9223372036854775807) || ($operator === '<' && $number === -9223372036854775807 - 1)) {
            return false;
        }
        if ($operator === '>' || $operator === '>=') {
            $minimum = is_int($number) ? ($operator === '>' ? $number + 1 : $number) : ($operator === '>' ? floor($number) + 1 : ceil($number));
            $bounds['min'] = $bounds['min'] === null ? $minimum : max($minimum, $bounds['min']);
        }
        if ($operator === '<' || $operator === '<=') {
            $maximum = is_int($number) ? ($operator === '<' ? $number - 1 : $number) : ($operator === '<' ? ceil($number) - 1 : floor($number));
            $bounds['max'] = $bounds['max'] === null ? $maximum : min($maximum, $bounds['max']);
        }
        return true;
    }
}
