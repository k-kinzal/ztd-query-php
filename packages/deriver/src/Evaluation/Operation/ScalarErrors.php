<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Operation;

use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Retains runtime error paths for scalar operations whose operands remain symbolic.
 * @visibility root
 */
final class ScalarErrors
{
    /**
     * @param Context $context Guard refinement and diagnostics
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Splits possible numeric failures while retaining the successfully evaluated expression.
     * @param Instruction $instruction Scalar operation
     * @param State $state Normal symbolic result
     * @return list<State> Normal and possible exceptional states
     */
    public function paths(Instruction $instruction, State $state): array
    {
        $left = $state->value($instruction->operands[0] ?? '');
        $right = $state->value($instruction->operands[1] ?? '');
        if ($instruction->operation === 'unary') {
            return $this->unary($instruction, $state, $left);
        }
        if (!$this->eligible($instruction, $state, $left, $right)) {
            return [$state];
        }
        $paths = [$state];
        if (in_array($instruction->name, ['/', '%', '<<', '>>'], true)) {
            $shift = in_array($instruction->name, ['<<', '>>'], true);
            $paths = $this->split($state, $this->predicate($instruction, $right), $shift ? 'ArithmeticError' : 'DivisionByZeroError');
        }
        if ($this->mayTypeError($instruction, $left, $right)) {
            $exception = $state->fork();
            $exception->completion = new Completion('throw', new Term('throwable', 'TypeError'));
            $paths[] = $exception;
            $this->context->frontier('WIDENED', $instruction->source, 'symbolic-numeric-conversion', [$left, $right]);
        }
        return $paths;
    }

    /**
     * Distinguishes the failing predicate from successful execution using ordinary guards.
     * @param State $state Evaluated expression
     * @param Term $predicate Condition under which the operation throws
     * @param string $exception Throwable class
     * @return list<State> Feasible normal and failing paths
     */
    public function split(State $state, Term $predicate, string $exception): array
    {
        $paths = [];
        foreach ([false, true] as $fails) {
            $path = $state->fork();
            if ((new Constraints($this->context))->assume($path, $predicate, $fails)) {
                if ($fails) {
                    $path->completion = new Completion('throw', new Term('throwable', $exception));
                }
                $paths[] = $path;
            }
        }
        return $paths;
    }

    /**
     * Proves that ordinary scalar numeric conversion cannot throw TypeError.
     * @param Term $value Operand
     * @return bool Whether every represented value is numeric-compatible
     */
    public function numeric(Term $value): bool
    {
        if ($value->kind === 'constant') {
            return !is_string($value->literal) || is_numeric($value->literal);
        }
        return array_diff(explode('|', (string) ($value->attributes['type'] ?? 'mixed')), ['int', 'float', 'bool', 'true', 'false', 'null']) === [];
    }

    /**
     * Recognizes numeric operations that have not already completed concretely.
     * @param Instruction $instruction Operation
     * @param State $state Evaluated result
     * @param Term $left Left operand
     * @param Term $right Right operand
     * @return bool Whether symbolic error alternatives are needed
     */
    public function eligible(Instruction $instruction, State $state, Term $left, Term $right): bool
    {
        return $instruction->operation === 'binary' && in_array($instruction->name, ['+', '-', '*', '/', '%', '**', '<<', '>>', '&', '|', '^'], true) && $state->value($instruction->result)->kind !== 'throwable' && !($left->kind === 'constant' && $right->kind === 'constant');
    }

    /**
     * Constructs a zero-divisor or negative-shift guard after the target conversion.
     * @param Instruction $instruction Division, remainder, or shift
     * @param Term $right Original divisor or shift count
     * @return Term Runtime failure predicate
     */
    public function predicate(Instruction $instruction, Term $right): Term
    {
        $integral = $instruction->name !== '/';
        $divisor = $integral && ($right->attributes['type'] ?? '') !== 'int' ? (new Operations())->cast('int', $right) : $right;
        $shift = in_array($instruction->name, ['<<', '>>'], true);
        $operator = $shift ? '<' : (!$integral && ($right->attributes['type'] ?? '') !== 'int' ? '==' : '===');
        return (new Operations())->binary($operator, $divisor, Term::constant(0));
    }

    /**
     * Excludes proven array union and byte-string bitwise cases from numeric coercion errors.
     * @param Instruction $instruction Numeric or bitwise operation
     * @param Term $left Left operand
     * @param Term $right Right operand
     * @return bool Whether an operand can reject numeric conversion
     */
    public function mayTypeError(Instruction $instruction, Term $left, Term $right): bool
    {
        if ($instruction->name === '+' && $left->kind === 'array' && $right->kind === 'array') {
            return false;
        }
        if (in_array($instruction->name, ['&', '|', '^'], true) && ($left->attributes['type'] ?? '') === 'string' && ($right->attributes['type'] ?? '') === 'string') {
            return false;
        }
        return !$this->numeric($left) || !$this->numeric($right);
    }

    /**
     * Retains symbolic unary numeric and bitwise type failures.
     * @param Instruction $instruction Unary operation
     * @param State $state Normal result
     * @param Term $value Original operand
     * @return list<State> Normal result and possible TypeError
     */
    public function unary(Instruction $instruction, State $state, Term $value): array
    {
        if ($value->kind === 'constant' || $state->value($instruction->result)->kind === 'throwable') {
            return [$state];
        }
        $valid = match ($instruction->name) {
            'Expr_UnaryPlus', 'Expr_UnaryMinus' => $this->numeric($value),
            'Expr_BitwiseNot' => array_diff(explode('|', (string) ($value->attributes['type'] ?? 'mixed')), ['int', 'string']) === [],
            default => true,
        };
        if ($valid) {
            return [$state];
        }
        $exception = $state->fork();
        $exception->completion = new Completion('throw', new Term('throwable', 'TypeError'));
        $this->context->frontier('WIDENED', $instruction->source, 'symbolic-unary-conversion', [$value]);
        return [$state, $exception];
    }
}
