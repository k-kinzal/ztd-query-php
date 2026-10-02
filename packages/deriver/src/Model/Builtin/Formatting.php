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
     * @param bool $vector Whether the arguments come from a vsprintf array, which reports missing items as ValueError
     * @return Term Formatted string, exception, or explicit unsupported case
     */
    public function apply(array $values, bool $vector = false): Term
    {
        $format = $values[0] ?? Term::constant(null);
        $arguments = $values[1] ?? Term::array([]);
        if (!is_string($format->literal) || $format->kind !== 'constant' || $arguments->kind !== 'array' || ($arguments->attributes['open'] ?? false) === true) {
            return Term::opaque('UNSUPPORTED_MODEL_CASE', 'string', $values);
        }
        return $this->render($format->literal, $format->isSecret(), array_values($arguments->operands), false, $vector) ?? Term::opaque('UNSUPPORTED_MODEL_CASE', 'string', $values);
    }

    /**
     * Formats the known leading literal of a dynamic format when no conversion specifier crosses into the unknown rest.
     * @param list<Term> $values Bound format and argument array
     * @return Term|null Text produced before the unknown rest, or null when no sound prefix is known
     */
    public function prefix(array $values): ?Term
    {
        $format = $values[0] ?? Term::constant(null);
        $arguments = $values[1] ?? Term::array([]);
        if ($format->kind !== 'concat' || $arguments->kind !== 'array' || ($arguments->attributes['open'] ?? false) === true) {
            return null;
        }
        $known = '';
        $secret = false;
        $pending = [$format];
        while ($pending !== []) {
            $part = array_pop($pending);
            if ($part->kind === 'concat') {
                array_push($pending, ...array_reverse($part->operands));
                continue;
            }
            if ($part->kind !== 'constant' || !is_string($part->literal)) {
                break;
            }
            $known .= $part->literal;
            $secret = $secret || $part->isSecret();
        }
        $result = $known === '' ? null : $this->render($known, $secret, array_values($arguments->operands), true, false);
        return $result === null || in_array($result->kind, ['throwable', 'opaque'], true) ? null : $result;
    }

    /**
     * Applies supported specifiers in order, deferring missing arguments as PHP does until the format is exhausted.
     * @param string $format Literal format text
     * @param bool $secret Whether the format is confidential
     * @param list<Term> $arguments Arguments in positional order
     * @param bool $partial Whether text after the format may continue it, so a trailing specifier is left unformatted
     * @param bool $vector Whether missing items are reported as ValueError
     * @return Term|null Formatted text, exception, explicit residual, or null for unsupported syntax, values, or an undecidable partial exception
     */
    public function render(string $format, bool $secret, array $arguments, bool $partial, bool $vector): ?Term
    {
        $result = Term::constant('', $secret);
        $next = 0;
        $position = 0;
        $missing = false;
        $semantics = new Operations($this->floatPrecision);
        while ($position < strlen($format)) {
            $percent = strpos($format, '%', $position);
            if ($percent === false) {
                $result = $semantics->binary('.', $result, Term::constant(substr($format, $position)));
                break;
            }
            $result = $semantics->binary('.', $result, Term::constant(substr($format, $position, $percent - $position)));
            if ($partial && preg_match('/\A%(?:\d+\$?)?\z/', substr($format, $percent)) === 1) {
                break;
            }
            if (preg_match('/\A%(?:(\d+)\$)?([sd%])/', substr($format, $percent), $match) !== 1) {
                return null;
            }
            $position = $percent + strlen($match[0]);
            if ($match[0] === '%%') {
                $result = $semantics->binary('.', $result, Term::constant('%'));
                continue;
            }
            $number = $match[1] === '' ? null : ltrim($match[1], '0');
            if ($number !== null && ($number === '' || strlen($number) > 10 || (int) $number >= 2147483647)) {
                return new Term('throwable', 'ValueError');
            }
            $index = $number === null ? $next++ : (int) $number - 1;
            if (!isset($arguments[$index])) {
                $missing = true;
                continue;
            }
            $part = $this->convert($match[2], $arguments[$index]);
            if ($part === null || $part->kind === 'opaque') {
                return $part;
            }
            $result = $semantics->binary('.', $result, $part);
        }
        if ($missing) {
            return $partial ? null : new Term('throwable', $vector ? 'ValueError' : 'ArgumentCountError');
        }
        return $result;
    }

    /**
     * Converts one scalar argument for a string, decimal, or argument-numbered percent specifier.
     * @param string $conversion Conversion character
     * @param Term $argument Selected argument
     * @return Term|null Converted text, an explicit residual, or null when conversion may run user code
     */
    public function convert(string $conversion, Term $argument): ?Term
    {
        if ($conversion === '%') {
            return Term::constant('%');
        }
        if ($argument->kind !== 'constant' && (new TypePredicates())->apply('is_scalar', $argument)->literal !== true) {
            return null;
        }
        return (new Operations($this->floatPrecision))->cast($conversion === 's' ? 'string' : 'int', $argument);
    }
}
