<?php

declare(strict_types=1);

namespace Deriver\Model\Builtin;

use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Evaluates byte-string operations without reading host locale or environment.
 * @visibility root
 */
final class StringFunctions
{
    /**
     * @param int|null $floatPrecision Captured target precision for float-to-string conversion; null when unknown
     */
    public function __construct(public readonly ?int $floatPrecision = null)
    {
    }

    /**
     * Applies a supported string operation or retains its symbolic relationship.
     * @param string $name Function name
     * @param list<Term> $values Bound arguments
     * @return Term String, array, length, or throwable
     */
    public function apply(string $name, array $values): Term
    {
        if ($name === 'implode' || $name === 'join') {
            return $this->implode($values);
        }
        if ($name === 'explode') {
            return $this->split($values);
        }
        if ($name === 'substr') {
            return $this->substring($values);
        }
        if (!$this->validArguments($name, $values)) {
            return Term::opaque('UNSUPPORTED_MODEL_CASE', 'string', $values);
        }
        $a = $values[0] ?? Term::constant(null);
        if ($a->kind !== 'constant' || !is_string($a->literal)) {
            return new Term('intrinsic', $name, $values, ['type' => $name === 'strlen' ? 'int' : 'string']);
        }
        $string = $a->literal;
        if ($name === 'strlen') {
            return Term::constant(strlen($string), $a->isSecret());
        }
        return $this->transform($name, $a, $values);
    }

    /**
     * Keeps unknown pieces as concatenation operands with their original identity.
     * @param list<Term> $values Separator and array
     * @return Term Joined string
     */
    public function implode(array $values): Term
    {
        $separator = $values[0] ?? Term::constant('');
        $array = $values[1] ?? Term::constant(null);
        if ($array->kind === 'constant' && $array->literal === null) {
            if ((new TypePredicates())->apply('is_array', $separator)->literal === false) {
                return new Term('throwable', 'TypeError');
            }
            return $this->implode([Term::constant(''), $separator]);
        }
        if ($separator->kind === 'array' && $array->kind === 'array') {
            return new Term('throwable', 'TypeError');
        }
        if ($array->kind !== 'array' || ($array->attributes['open'] ?? false) === true || (new TypePredicates())->apply('is_string', $separator)->literal !== true) {
            return Term::opaque('UNSUPPORTED_MODEL_CASE', 'string', $values);
        }
        $result = Term::constant('');
        $first = true;
        foreach ($array->operands as $part) {
            if ($part->kind !== 'constant' && (new TypePredicates())->apply('is_scalar', $part)->literal !== true) {
                return Term::opaque('UNSUPPORTED_MODEL_CASE', 'string', $values);
            }
            $part = (new Operations($this->floatPrecision))->cast('string', $part);
            if ($part->kind === 'opaque') {
                return $part;
            }
            if (!$first) {
                $result = (new Operations($this->floatPrecision))->binary('.', $result, $separator);
            }
            $result = (new Operations($this->floatPrecision))->binary('.', $result, $part);
            $first = false;
        }
        return $result;
    }
    /**
     * Evaluates byte substring offsets and nullable lengths.
     * @param list<Term> $values String, offset, and length
     * @return Term Substring or residual
     */
    public function substring(array $values): Term
    {
        $string = $values[0] ?? Term::constant(null);
        $offset = $values[1] ?? Term::constant(null);
        $length = $values[2] ?? Term::constant(null);
        if ($string->kind === 'constant' && is_string($string->literal) && $offset->kind === 'constant' && is_int($offset->literal) && $length->kind === 'constant' && (is_int($length->literal) || $length->literal === null)) {
            return Term::constant(substr($string->literal, $offset->literal, $length->literal), $string->isSecret() || $offset->isSecret() || $length->isSecret());
        }
        return Term::opaque('UNSUPPORTED_MODEL_CASE', 'string', $values);
    }

    /**
     * Evaluates literal byte separators, including empty-separator exceptions.
     * @param list<Term> $values Separator, string, and limit
     * @return Term Split array, ValueError, or residual
     */
    public function split(array $values): Term
    {
        $separator = $values[0] ?? Term::constant(null);
        $string = $values[1] ?? Term::constant(null);
        $limit = $values[2] ?? Term::constant(9223372036854775807);
        if ($separator->kind === 'constant' && is_string($separator->literal) && $string->kind === 'constant' && is_string($string->literal) && $limit->kind === 'constant' && is_int($limit->literal)) {
            return $separator->literal === '' ? new Term('throwable', 'ValueError') : Term::fromNative(explode($separator->literal, $string->literal, $limit->literal), $separator->isSecret() || $string->isSecret() || $limit->isSecret());
        }
        return Term::opaque('UNSUPPORTED_MODEL_CASE', 'array', $values);
    }

    /**
     * Transforms concrete byte strings with explicit optional arguments.
     * @param string $name Operation name
     * @param Term $a Concrete byte string
     * @param list<Term> $values Bound arguments
     * @return Term Transformed value
     */
    public function transform(string $name, Term $a, array $values): Term
    {
        if (!is_string($a->literal)) {
            return Term::opaque('UNSUPPORTED_MODEL_CASE', 'string', $values);
        }
        $string = $a->literal;
        if (!$this->validArguments($name, $values)) {
            return Term::opaque('UNSUPPORTED_MODEL_CASE', 'string', $values);
        }
        if (in_array($name, ['strtolower', 'strtoupper', 'ucfirst', 'lcfirst', 'trim', 'ltrim', 'rtrim'], true)) {
            return Term::constant(match ($name) {
                'strtolower' => strtr($string, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'strtoupper' => strtr($string, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 'ucfirst' => $string === '' ? '' : strtr($string[0], 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ') . substr($string, 1), 'lcfirst' => $string === '' ? '' : strtr($string[0], 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') . substr($string, 1), 'ltrim' => ltrim($string, (string) $values[1]->literal), 'rtrim' => rtrim($string, (string) $values[1]->literal), 'trim' => trim($string, is_string($values[1]->literal ?? null) ? $values[1]->literal : " \n\r\t\v\0")
            }, $a->isSecret() || ($values[1] ?? Term::constant(null))->isSecret());
        }
        return match ($name) {
            'substr' => $this->substring($values),
            'explode' => $this->split($values),
            default => Term::opaque('UNSUPPORTED_MODEL_CASE', dependencies: $values),
        };
    }

    /**
     * Checks trimming masks without asking the host to emit diagnostics.
     * @param string $name Transformation
     * @param list<Term> $values Bound arguments
     * @return bool Whether the mask is concrete and well formed
     */
    public function validArguments(string $name, array $values): bool
    {
        return !in_array($name, ['trim', 'ltrim', 'rtrim'], true) || (($values[1]->kind ?? '') === 'constant' && is_string($values[1]->literal) && $this->validMask($values[1]->literal));
    }

    /**
     * Rejects malformed byte ranges before trim can emit a host warning.
     * @param string $mask Target character mask
     * @return bool Whether every range has ordered endpoints
     */
    public function validMask(string $mask): bool
    {
        $length = strlen($mask);
        for ($offset = 0; $offset < $length; $offset++) {
            if ($offset + 3 < $length && $mask[$offset + 1] === '.' && $mask[$offset + 2] === '.' && ord($mask[$offset + 3]) >= ord($mask[$offset])) {
                $offset += 3;
            } elseif ($offset + 1 < $length && $mask[$offset] === '.' && $mask[$offset + 1] === '.') {
                return false;
            }
        }
        return true;
    }
}
