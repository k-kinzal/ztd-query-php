<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Identifier;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
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
            return new Name($parts[0], $parts[1]);
        }, \Tests\Generator\Operands::tuple(
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
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<string> $value The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\NameQuote|null> $quote The generated semantic operand
     * @return ErisGenerator<Name>
     */
    public static function fromOperands(ErisGenerator $value, ErisGenerator $quote): ErisGenerator
    {
        return Generators::map(static fn (array $parts): Name => new Name(value: $parts[0], quote: $parts[1]), \Tests\Generator\Operands::tuple($value, $quote));
    }

}
