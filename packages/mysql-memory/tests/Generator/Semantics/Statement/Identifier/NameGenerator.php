<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Identifier;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Composes decoded names and their delimiter choice through Eris.
 *
 * @implements ErisGenerator<Name>
 */
final class NameGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<Name>
     */
    private ErisGenerator $delegate;

    /**
     * @param ErisGenerator<string>|null $values Decoded names valid for the consuming SQL role
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\NameQuote|null>|null $quotes Delimiters valid for the language profile
     */
    public function __construct(?ErisGenerator $values = null, ?ErisGenerator $quotes = null)
    {
        $this->delegate = Generators::map(static function (array $parts): Name {
            if (!is_string($parts[0]) || ($parts[1] !== null && !$parts[1] instanceof \SqlSemantics\Statement\Identifier\NameQuote)) {
                throw new LogicException('A name generator composes decoded text and an optional delimiter.');
            }
            return new Name($parts[0], $parts[1]);
        }, Generators::tuple(
            $values ?? Generators::map(static fn (int $number): string => 'name_' . abs($number), Generators::int()),
            $quotes ?? new NameQuoteGenerator(),
        ));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<Name>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Name>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
