<?php

declare(strict_types=1);

namespace Deriver\Model\Builtin;

use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Formats the verified percent, string, and decimal sprintf conversions symbolically.
 * @visibility root
 */
final class Formatting
{
    /**
     * @param int|null $floatPrecision Captured target precision for float-to-string conversion; null when unknown
     */
    public function __construct(public readonly ?int $floatPrecision = null)
    {
    }

    /**
     * Keeps dynamic substitutions as expressions and rejects unsupported format syntax.
     * @param list<Term> $values Bound format and variadic argument array
     * @return Term Formatted string, exception, or explicit unsupported case
     */
    public function apply(array $values): Term
    {
        $format = $values[0] ?? Term::constant(null);
        $arguments = $values[1] ?? Term::array([]);
        if (!is_string($format->literal) || $format->kind !== 'constant' || $arguments->kind !== 'array' || ($arguments->attributes['open'] ?? false) === true) {
            return Term::opaque('UNSUPPORTED_MODEL_CASE', 'string', $values);
        }
        $result = Term::constant('', $format->isSecret());
        $next = 0;
        $position = 0;
        $semantics = new Operations($this->floatPrecision);
        while ($position < strlen($format->literal)) {
            $percent = strpos($format->literal, '%', $position);
            if ($percent === false) {
                return $semantics->binary('.', $result, Term::constant(substr($format->literal, $position)));
            }
            $result = $semantics->binary('.', $result, Term::constant(substr($format->literal, $position, $percent - $position)));
            if (preg_match('/\A%(?:(\d+)\$)?([sd%])/', substr($format->literal, $percent), $match) !== 1) {
                return Term::opaque('UNSUPPORTED_MODEL_CASE', 'string', $values);
            }
            $position = $percent + strlen($match[0]);
            if ($match[2] === '%') {
                $result = $semantics->binary('.', $result, Term::constant('%'));
                continue;
            }
            $index = $match[1] === '' ? $next++ : (int) $match[1] - 1;
            if (!isset($arguments->operands[$index])) {
                return new Term('throwable', 'ArgumentCountError');
            }
            $argument = $arguments->operands[$index];
            if ($argument->kind !== 'constant' && (new TypePredicates())->apply('is_scalar', $argument)->literal !== true) {
                return Term::opaque('UNSUPPORTED_MODEL_CASE', 'string', $values);
            }
            $part = $semantics->cast($match[2] === 's' ? 'string' : 'int', $argument);
            if ($part->kind === 'opaque') {
                return $part;
            }
            $result = $semantics->binary('.', $result, $part);
        }
        return $result;
    }
}
