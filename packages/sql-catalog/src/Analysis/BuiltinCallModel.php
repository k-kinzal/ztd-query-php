<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis;

use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Evaluation\PatternTerm;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

/**
 * What the PHP functions that shape query text do to the values they are given.
 *
 * Queries are assembled with a small, stable set of string functions. Modelling
 * those keeps a statement resolved that would otherwise become a gap the moment
 * it passed through `sprintf()` or `implode()`.
 *
 * @visibility root
 */
final class BuiltinCallModel
{
    private const STRING_RESULTS = [
        'json_encode' => true,
        'addslashes' => true,
        'htmlspecialchars' => true,
        'str_pad' => true,
        'substr' => true,
        'number_format' => true,
        'date' => true,
        'ucwords' => true,
        'nl2br' => true,
        'serialize' => true,
    ];

    /**
     * Whether the function is one the model knows how to evaluate.
     */
    public function supports(string $name): bool
    {
        return in_array($this->normalize($name), [
            'sprintf',
            'vsprintf',
            'implode',
            'join',
            'str_repeat',
            'strtolower',
            'strtoupper',
            'ucfirst',
            'lcfirst',
            'trim',
            'ltrim',
            'rtrim',
            'str_replace',
            'strval',
            'intval',
            'count',
            'strlen',
        ], true) || isset(self::STRING_RESULTS[$this->normalize($name)]);
    }

    /**
     * The result of calling the function with the given argument domains.
     *
     * @param list<Domain> $arguments
     */
    public function evaluate(string $name, array $arguments): Domain
    {
        $function = $this->normalize($name);

        return match ($function) {
            'sprintf' => $this->sprintf(array_slice($arguments, 1), $arguments[0] ?? Domain::unknown()),
            'vsprintf' => $this->vsprintf($arguments),
            'implode', 'join' => $this->implode($arguments),
            'str_repeat' => $this->repeat($arguments),
            'strtolower', 'strtoupper', 'ucfirst', 'lcfirst', 'trim', 'ltrim', 'rtrim'
                => $this->transform($function, $arguments),
            'str_replace' => $this->replace($arguments),
            'strval' => $arguments[0] ?? Domain::unknown(),
            'intval', 'count', 'strlen' => Domain::opaque(TypeShape::of(['int']), Origin::Call, $function),
            default => Domain::opaque(TypeShape::of(['string']), Origin::Call, $function),
        };
    }

    /**
     * The function name without its namespace, in lower case.
     */
    public function normalize(string $name): string
    {
        $parts = explode('\\', ltrim($name, '\\'));

        return strtolower($parts[count($parts) - 1]);
    }

    /**
     * The result of `sprintf()`, resolved as far as the format and the arguments allow.
     *
     * A format the analyzer knows only in part — `"SELECT * FROM $table WHERE
     * id IN (%s)"` — still has conversions in the parts it knows, so those are
     * filled in and the gaps are carried through, rather than the whole result
     * becoming one gap because one piece of the format was not fixed.
     *
     * @param list<Domain> $arguments
     */
    public function sprintf(array $arguments, Domain $format): Domain
    {
        $result = null;
        foreach ($format->terms as $term) {
            $value = $term instanceof LiteralTerm || $term instanceof PatternTerm
                ? $this->formatPattern($term->toPattern(), $arguments)
                : Domain::opaque(TypeShape::of(['string']), $this->originOf($format, Origin::Call), 'sprintf');
            $result = $result === null ? $value : $result->union($value);
        }

        return $result ?? Domain::opaque(TypeShape::of(['string']), Origin::Call, 'sprintf');
    }

    /**
     * A known or partly known format with its conversions filled in by the arguments.
     *
     * @param list<Domain> $arguments
     */
    public function formatPattern(TextPattern $format, array $arguments): Domain
    {
        $result = Domain::literal('');
        $index = 0;
        foreach ($format->segments as $segment) {
            if (!$segment instanceof LiteralText) {
                $result = $result->concat(Domain::of(Domain::asTerm(TextPattern::fromSegments([$segment]))));
                continue;
            }
            foreach ($this->splitFormat($segment->text) as $piece) {
                if (!str_starts_with($piece, '%') || $piece === '%%') {
                    $result = $result->concat(Domain::literal($piece === '%%' ? '%' : $piece));
                    continue;
                }
                $result = $result->concat(
                    $arguments[$index] ?? Domain::opaque(TypeShape::unknown(), Origin::Call, 'sprintf'),
                );
                $index++;
            }
        }

        return $result;
    }

    /**
     * Where the unresolved part of a value comes from, or the fallback when nothing in it says.
     */
    public function originOf(Domain $domain, Origin $fallback): Origin
    {
        foreach ($domain->terms as $term) {
            if ($term instanceof OpaqueTerm) {
                return $term->origin;
            }
        }

        return $fallback;
    }

    /**
     * The result of `vsprintf()`, which takes its arguments as one array.
     *
     * @param list<Domain> $arguments
     */
    public function vsprintf(array $arguments): Domain
    {
        $values = ($arguments[1] ?? Domain::unknown())->soleArray();
        if ($values === null) {
            return Domain::opaque(TypeShape::of(['string']), Origin::Call, 'vsprintf');
        }

        return $this->sprintf($values->positional(), $arguments[0] ?? Domain::unknown());
    }

    /**
     * A format string split into literal runs and conversion specifications.
     *
     * @return list<string>
     */
    public function splitFormat(string $format): array
    {
        $pieces = preg_split('/(%%|%[-+ 0\']*\d*(?:\.\d+)?[bcdeEfFgGosuxX])/', $format, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($pieces === false) {
            return [$format];
        }

        return array_values(array_filter($pieces, static fn (string $piece): bool => $piece !== ''));
    }

    /**
     * The result of `implode()`, resolved when the array is known element by element.
     *
     * @param list<Domain> $arguments
     */
    public function implode(array $arguments): Domain
    {
        $glue = count($arguments) > 1 ? $arguments[0] : Domain::literal('');
        $array = $this->arrayArgument($arguments);
        if ($array === null) {
            $pieces = $arguments[count($arguments) - 1] ?? Domain::unknown();

            return Domain::opaque(TypeShape::of(['string']), $this->originOf($pieces, Origin::Call), 'implode');
        }

        $result = Domain::literal('');
        $first = true;
        foreach ($array->positional() as $element) {
            $result = $first ? $element : $result->concat($glue)->concat($element);
            $first = false;
        }

        return $array->complete ? $result : $result->concat(Domain::opaque(TypeShape::of(['string']), Origin::Call, 'implode'));
    }

    /**
     * The array argument of an implode call, whichever position it is written in.
     *
     * @param list<Domain> $arguments
     */
    public function arrayArgument(array $arguments): ?ArrayTerm
    {
        foreach ($arguments as $argument) {
            $array = $argument->soleArray();
            if ($array !== null) {
                return $array;
            }
        }

        return null;
    }

    /**
     * The result of `str_repeat()`, resolved when both arguments are known.
     *
     * @param list<Domain> $arguments
     */
    public function repeat(array $arguments): Domain
    {
        $subject = ($arguments[0] ?? Domain::unknown())->soleLiteral();
        $times = ($arguments[1] ?? Domain::unknown())->soleLiteral();
        if ($subject === null || $times === null || !is_int($times->value) || $times->value < 0 || $times->value > 1000) {
            return Domain::opaque(TypeShape::of(['string']), Origin::Call, 'str_repeat');
        }

        return Domain::literal(str_repeat($subject->toText(), $times->value));
    }

    /**
     * The result of a one-argument string transformation.
     *
     * @param list<Domain> $arguments
     */
    public function transform(string $function, array $arguments): Domain
    {
        $subject = ($arguments[0] ?? Domain::unknown())->soleLiteral();
        if ($subject === null) {
            return Domain::opaque(TypeShape::of(['string']), Origin::Call, $function);
        }
        $text = $subject->toText();

        return Domain::literal(match ($function) {
            'strtolower' => strtolower($text),
            'strtoupper' => strtoupper($text),
            'ucfirst' => ucfirst($text),
            'lcfirst' => lcfirst($text),
            'ltrim' => ltrim($text),
            'rtrim' => rtrim($text),
            default => trim($text),
        });
    }

    /**
     * The result of `str_replace()`, resolved when every argument is a known string.
     *
     * @param list<Domain> $arguments
     */
    public function replace(array $arguments): Domain
    {
        $search = ($arguments[0] ?? Domain::unknown())->soleLiteral();
        $replace = ($arguments[1] ?? Domain::unknown())->soleLiteral();
        $subject = ($arguments[2] ?? Domain::unknown())->soleLiteral();
        if ($search === null || $replace === null || $subject === null) {
            return Domain::opaque(TypeShape::of(['string']), Origin::Call, 'str_replace');
        }

        return Domain::literal(str_replace($search->toText(), $replace->toText(), $subject->toText()));
    }
}
