<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Literal;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RadixLiteral values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral>
 */
final class RadixLiteralGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Radix> $radix Generated constructor operand
     * @param ErisGenerator<string> $digits Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $introducer Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\RadixNotation|null>|null $notation Generated constructor operand
     */
    public function __construct(ErisGenerator $radix, ErisGenerator $digits, ?ErisGenerator $introducer = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $notation = null)
    {
        $this->delegate = \Tests\Generator\Operands::bind(\Tests\Generator\Operands::tuple(
            $radix,
            $digits,
            $introducer ?? Generators::constant(null),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ), static function (array $parts) use ($notation): ErisGenerator {
            return Generators::map(static fn (?\SqlSemantics\Platform\MySql\Statement\Literal\RadixNotation $selected): \SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral => new \SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral(radix: $parts[0], digits: $parts[1], introducer: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4], notation: $selected), $notation ?? RadixNotationGenerator::forDigits($parts[0], $parts[1]));
        });
    }

    /**
     * Composes arbitrary binary values and valid notations into executable projections.
     *
     * @return ErisGenerator<\SqlSemantics\Statement\AnalyzedStatement>
     */
    public static function queries(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $literals = \Tests\Generator\Operands::bind(new RadixGenerator(), static function (\SqlSemantics\Platform\MySql\Statement\Literal\Radix $radix): ErisGenerator {
            $digits = Generators::map(static function (array $parts) use ($radix): string {
                [$number, $zeros, $empty] = $parts;
                return $empty ? '' : str_repeat('0', $zeros) . ($radix === \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal ? dechex($number) : decbin($number));
            }, \Tests\Generator\Operands::tuple(Generators::choose(0, 65535), Generators::choose(0, 3), Generators::bool()));
            return new self(Generators::constant($radix), $digits);
        });
        $statements = new \Tests\Generator\MySql\Statement\QueryRootGenerator(new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
            Generators::constant([]),
            Generators::vector(1, new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator($literals, new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator())),
        ));
        return new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $statements);
    }
}
