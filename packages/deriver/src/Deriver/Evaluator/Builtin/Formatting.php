<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Builtin;

use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\Term;

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
        if (!$vector && $arguments->kind === \Deriver\Deriver\Evaluator\TermKind::Array && array_filter(array_keys($arguments->operands), is_string(...)) !== []) {
            return new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'ArgumentCountError');
        }
        if (!is_string($format->literal) || $format->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant || $arguments->kind !== \Deriver\Deriver\Evaluator\TermKind::Array || ($arguments->attributes['open'] ?? false) === true) {
            return Term::opaque('UNSUPPORTED_EVALUATOR_CASE', 'string', $values);
        }
        return $this->render($format->literal, array_values($arguments->operands), false, $vector) ?? Term::opaque('UNSUPPORTED_EVALUATOR_CASE', 'string', $values);
    }

    /**
     * Formats the known leading literal of a dynamic format when no conversion specifier crosses into the unknown rest.
     * @param list<Term> $values Bound format and argument array
     * @param bool $vector Whether the arguments come from a vsprintf array, whose string keys are accepted
     * @return Term|null Text produced before the unknown rest, or null when no sound prefix is known
     */
    public function prefix(array $values, bool $vector = false): ?Term
    {
        $format = $values[0] ?? Term::constant(null);
        $arguments = $values[1] ?? Term::array([]);
        if ($format->kind !== \Deriver\Deriver\Evaluator\TermKind::Concat || $arguments->kind !== \Deriver\Deriver\Evaluator\TermKind::Array || ($arguments->attributes['open'] ?? false) === true || !$vector && array_filter(array_keys($arguments->operands), is_string(...)) !== []) {
            return null;
        }
        $known = $this->leading($format);
        $result = $known === '' ? null : $this->render($known, array_values($arguments->operands), true, $vector);
        return $result === null || in_array($result->kind, [\Deriver\Deriver\Evaluator\TermKind::Throwable, \Deriver\Deriver\Evaluator\TermKind::Opaque], true) ? null : $result;
    }

    /**
     * Reads leading literal text through concatenation and no-op string casts.
     * @param Term $format Partially known string format
     * @return string Literal prefix
     */
    public function leading(Term $format): string
    {
        $known = '';
        $pending = [$format];
        while ($pending !== []) {
            $part = array_pop($pending);
            if ($part->kind === \Deriver\Deriver\Evaluator\TermKind::Cast && $part->literal === 'string' && ($part->operands[0]->attributes['type'] ?? '') === 'string') {
                $pending[] = $part->operands[0];
                continue;
            }
            if ($part->kind === \Deriver\Deriver\Evaluator\TermKind::Concat) {
                array_push($pending, ...array_reverse($part->operands));
                continue;
            }
            if ($part->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant || !is_string($part->literal)) {
                break;
            }
            $known .= $part->literal;
        }
        return $known;
    }

    /**
     * Scans the format as PHP 8.3 does: a missing argument is reported after the whole format, and its conversion character is read again as format text.
     * @param string $format Literal format text
     * @param list<Term> $arguments Arguments in positional order
     * @param bool $partial Whether text after the format may continue it, so a specifier reaching the end is left unformatted
     * @param bool $vector Whether missing items are reported as ValueError
     * @return Term|null Formatted text, exception, explicit residual, or null for unsupported syntax, values, or an undecidable partial exception
     */
    public function render(string $format, array $arguments, bool $partial, bool $vector): ?Term
    {
        $result = Term::constant('');
        $next = 0;
        $position = 0;
        $missing = false;
        $semantics = new Operations($this->floatPrecision);
        while ($position < strlen($format)) {
            $percent = strpos($format, '%', $position);
            $text = $percent === false ? substr($format, $position) : substr($format, $position, $percent - $position);
            $result = $semantics->binary('.', $result, Term::constant($text));
            if ($percent === false) {
                break;
            }
            if (($format[$percent + 1] ?? '') === '%') {
                $result = $semantics->binary('.', $result, Term::constant('%'));
                $position = $percent + 2;
                continue;
            }
            if ($partial && preg_match('/\A[0-9]*\$?\z/', substr($format, $percent + 1)) === 1) {
                return $result;
            }
            $specifier = $this->specifier($format, $percent + 1);
            if ($specifier === null) {
                return null;
            }
            if ($specifier['invalid']) {
                return new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'ValueError');
            }
            $index = $specifier['argument'] ?? $next++;
            $position = $specifier['end'];
            if (!isset($arguments[$index])) {
                $missing = true;
                continue;
            }
            if ($specifier['conversion'] === '') {
                return new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'ValueError');
            }
            $part = $this->convert($specifier['conversion'], $arguments[$index]);
            if ($part === null || $part->kind === \Deriver\Deriver\Evaluator\TermKind::Opaque) {
                return $part;
            }
            $result = $semantics->binary('.', $result, $part);
            $position++;
        }
        if ($missing) {
            return $partial ? null : new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, $vector ? 'ValueError' : 'ArgumentCountError');
        }
        return $result;
    }

    /**
     * Parses an optional argument number and the conversion character after a percent sign.
     * @param string $format Format text
     * @param int $position Offset after the percent sign
     * @return array{argument: int|null, invalid: bool, end: int, conversion: string}|null Zero-based argument (null for the next one), whether the number is out of range, the conversion offset, and its character ('' at the end of the text); null for flags, width, precision, or an unsupported conversion
     */
    public function specifier(string $format, int $position): ?array
    {
        $argument = null;
        $invalid = false;
        if (preg_match('/\G([0-9]*)\$/', $format, $match, 0, $position) === 1) {
            $number = ltrim($match[1], '0');
            $invalid = $number === '' || strlen($number) > 10 || (int) $number >= 2147483647;
            $argument = $invalid ? null : (int) $number - 1;
            $position += strlen($match[0]);
        }
        $conversion = $format[$position] ?? '';
        if (!$invalid && !in_array($conversion, ['', 's', 'd', '%'], true)) {
            return null;
        }
        return ['argument' => $argument, 'invalid' => $invalid, 'end' => $position, 'conversion' => $conversion];
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
        if ($argument->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant && (new TypePredicates())->apply('is_scalar', $argument)->literal !== true) {
            return null;
        }
        return (new Operations($this->floatPrecision))->cast($conversion === 's' ? 'string' : 'int', $argument);
    }
}
