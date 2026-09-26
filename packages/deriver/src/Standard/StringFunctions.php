<?php

declare(strict_types=1);

namespace Deriver\Standard;

use Deriver\Internal\Value\PhpSemantics;
use Deriver\Value\Term;

/**
 * Evaluates byte-string operations without reading host locale or environment.
 * @visibility root
 */
final class StringFunctions
{
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
        if ($separator->kind === 'array' && $array->literal === null) {
            $array = $separator;
            $separator = Term::constant('');
        }
        if ($array->kind !== 'array' || ($array->attributes['open'] ?? false) === true) {
            return new Term('intrinsic', 'implode', $values, ['type' => 'string']);
        }
        $result = Term::constant('');
        $first = true;
        foreach ($array->operands as $part) {
            if ($part->kind !== 'constant' && (new TypePredicates())->apply('is_scalar', $part)->literal !== true) {
                return Term::opaque('UNSUPPORTED_MODEL_CASE', 'string', $values);
            }
            $part = (new PhpSemantics())->cast('string', $part);
            if ($part->kind === 'opaque') {
                return $part;
            }
            if (!$first) {
                $result = (new PhpSemantics())->binary('.', $result, $separator);
            }
            $result = (new PhpSemantics())->binary('.', $result, $part);
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
            return Term::constant(substr($string->literal, $offset->literal, $length->literal), $string->isSecret());
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
            return $separator->literal === '' ? new Term('throwable', 'ValueError') : Term::fromNative(explode($separator->literal, $string->literal, $limit->literal), $separator->isSecret() || $string->isSecret());
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
        if ($name === 'trim' && (!is_string($values[1]->literal ?? null) || ($values[1]->kind ?? '') !== 'constant')) {
            return Term::opaque('UNSUPPORTED_MODEL_CASE', 'string', $values);
        }
        if ($name === 'strtolower' || $name === 'strtoupper' || $name === 'trim') {
            return Term::constant(match ($name) {
                'strtolower' => strtr($string, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'strtoupper' => strtr($string, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 'trim' => trim($string, is_string($values[1]->literal ?? null) ? $values[1]->literal : " \n\r\t\v\0")
            }, $a->isSecret());
        }
        return match ($name) {
            'substr' => $this->substring($values),
            'explode' => $this->split($values),
            default => Term::opaque('UNSUPPORTED_MODEL_CASE', dependencies: $values),
        };
    }
}
