<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Offset;

use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Scalar\IntegerConversion;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * PHP 8.3 byte offsets, including context-dependent diagnostics and assignment results.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Strings
{
    /**
     * @param Context $context Target semantics and diagnostic collector
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Converts a string offset without accidentally applying array-key rules.
     * @param Term|null $key Evaluated offset or append syntax
     * @param Instruction $instruction Originating operation
     * @param bool $silent Whether existence semantics suppress invalid-key diagnostics
     * @return Term Integer index, absent value, throwable, or an explicit boundary
     */
    public function index(?Term $key, Instruction $instruction, bool $silent = false): Term
    {
        if ($key === null) {
            return new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Error');
        }
        if ($key->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant) {
            return in_array($key->kind, [\Deriver\Deriver\Evaluator\TermKind::Array, \Deriver\Deriver\Evaluator\TermKind::Object, \Deriver\Deriver\Evaluator\TermKind::Closure, \Deriver\Deriver\Evaluator\TermKind::Enum], true)
                ? new Term($silent ? \Deriver\Deriver\Evaluator\TermKind::Uninitialized : \Deriver\Deriver\Evaluator\TermKind::Throwable, $silent ? null : 'TypeError')
                : Term::opaque('OFFSET_OPERATION', 'int', [$key]);
        }
        $value = $key->literal;
        if (is_string($value)) {
            return $this->stringIndex($key, $instruction, $silent);
        }
        if (!is_int($value) && (!$silent || is_float($value) && (new IntegerConversion())->warning($value))) {
            $this->warning($instruction);
        }
        return (new Operations())->cast('int', $key);
    }

    /**
     * Distinguishes integer strings from leading-integer strings and decimal strings.
     * @param Term $key Known string key
     * @param Instruction $instruction Origin
     * @param bool $silent Whether this is an existence test
     * @return Term Converted index or invalid-key result
     */
    public function stringIndex(Term $key, Instruction $instruction, bool $silent): Term
    {
        $value = (string) $key->literal;
        $integer = preg_match('/\A\s*[+-]?[0-9]+\s*\z/D', $value) === 1;
        $floatPrefix = preg_match('/\A\s*[+-]?(?:[0-9]+\.[0-9]*|\.[0-9]+|[0-9]+[eE][+-]?[0-9]+)/', $value) === 1;
        if (!$integer && ($floatPrefix || preg_match('/\A\s*[+-]?[0-9]+/', $value) !== 1 || $silent)) {
            return $silent ? new Term(\Deriver\Deriver\Evaluator\TermKind::Uninitialized) : new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'TypeError');
        }
        preg_match('/\A\s*([+-]?)([0-9]+)/', $value, $parts);
        $digits = ltrim($parts[2] ?? '', '0');
        $limit = ($parts[1] ?? '') === '-' ? '9223372036854775808' : '9223372036854775807';
        if (strlen($digits) > 19 || strlen($digits) === 19 && strcmp($digits, $limit) > 0) {
            return $silent ? new Term(\Deriver\Deriver\Evaluator\TermKind::Uninitialized) : new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'TypeError');
        }
        if (!$integer) {
            $this->warning($instruction);
        }
        return Term::constant((int) $value);
    }

    /**
     * Reads one byte, retaining negative offsets and silent absence.
     * @param Term $string Known string
     * @param Term|null $key Original key
     * @param Instruction $instruction Read instruction
     * @param bool $silent Whether an absent byte is tested without a warning
     * @return Term Byte, missing value, or error
     */
    public function read(Term $string, ?Term $key, Instruction $instruction, bool $silent = false): Term
    {
        $index = $this->index($key, $instruction, $silent);
        if ($index->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant || !is_int($index->literal)) {
            return $index;
        }
        $bytes = (string) $string->literal;
        $position = $index->literal < 0 ? strlen($bytes) + $index->literal : $index->literal;
        if ($position < 0 || $position >= strlen($bytes)) {
            if (!$silent) {
                $this->warning($instruction);
            }
            return $silent ? new Term(\Deriver\Deriver\Evaluator\TermKind::Uninitialized) : Term::constant('');
        }
        return Term::constant($bytes[$position]);
    }

    /**
     * Computes the updated string and the distinct byte-valued assignment expression.
     * @param Term $string Previous string
     * @param Term|null $key Original offset
     * @param Term $assigned Right-hand value
     * @param Instruction $instruction Assignment source
     * @return array{value: Term, result: Term} Updated storage and expression result
     */
    public function write(Term $string, ?Term $key, Term $assigned, Instruction $instruction): array
    {
        $index = $this->index($key, $instruction);
        if ($index->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant || !is_int($index->literal)) {
            return ['value' => $string, 'result' => $index];
        }
        $bytes = (string) $string->literal;
        $position = $index->literal < 0 ? strlen($bytes) + $index->literal : $index->literal;
        if ($position < 0) {
            $this->warning($instruction);
            return ['value' => $string, 'result' => Term::constant(null)];
        }
        $converted = $this->convert($assigned, $instruction);
        if ($converted->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant || !is_string($converted->literal)) {
            return ['value' => $string, 'result' => $converted];
        }
        if ($converted->literal === '') {
            return ['value' => $string, 'result' => new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Error')];
        }
        if (strlen($converted->literal) > 1) {
            $this->warning($instruction);
        }
        $bytes = str_pad($bytes, $position + 1, ' ');
        $bytes[$position] = $converted->literal[0];
        return ['value' => Term::constant($bytes), 'result' => Term::constant($converted->literal[0])];
    }

    /**
     * Converts a right-hand value without executing application conversion methods.
     * @param Term $value Assigned value
     * @param Instruction $instruction Assignment source
     * @return Term String or a boundary for implicit calls and environment-dependent formatting
     */
    public function convert(Term $value, Instruction $instruction): Term
    {
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Array) {
            $this->warning($instruction);
            return Term::constant('Array');
        }
        $converted = (new Operations($this->context->configuration->target->floatPrecision))->cast('string', $value);
        return $converted->kind === \Deriver\Deriver\Evaluator\TermKind::Constant ? $converted : Term::opaque('OFFSET_OPERATION', 'string', [$value]);
    }

    /**
     * Records target diagnostics without emitting host warnings.
     * @param Instruction $instruction Operation that emits the diagnostic
     */
    public function warning(Instruction $instruction): void
    {
        $this->context->frontier('PHP_WARNING', $instruction->source, 'offset-diagnostic');
    }
}
