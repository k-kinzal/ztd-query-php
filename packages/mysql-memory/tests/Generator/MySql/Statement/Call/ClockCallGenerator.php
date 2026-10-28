<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Composition;

/**
 * Composes ClockCall values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\ClockCall>
 */
final class ClockCallGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\ClockCall> */
    use Composition;

    /**
     * Composes synonym and canonical projections of the same statement clock.
     *
     * @return ErisGenerator<\SqlSemantics\Statement\AnalyzedStatement>
     */
    public static function queries(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $calls = \Tests\Generator\Operands::bind(new Keyword\ClockAliasGenerator(), static function (M\Call\Keyword\ClockAlias $alias): ErisGenerator {
            $precisions = $alias->clock()->precise()
                ? Generators::oneOf(Generators::constant(null), new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 6))))
                : Generators::constant(null);
            return \Tests\Generator\Operands::bind($precisions, static fn (?M\Literal\Numeral $precision): ErisGenerator => new self(
                Generators::constant($alias->clock()),
                Generators::constant($precision),
                $precision === null ? new \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator() : Generators::constant(M\Expression\OptionalWords::Written),
                alias: Generators::constant($alias),
            ));
        });
        $queries = Generators::map(static function (M\Call\ClockCall $call): M\QueryRoot {
            $canonical = new M\Call\ClockCall($call->clock, $call->precision === null ? null : new M\Literal\Numeral($call->precision->text));
            return new M\QueryRoot(new M\Query\Select([], [
                new M\Query\SelectExpression($call, new Name('selected')),
                new M\Query\SelectExpression($canonical, new Name('canonical')),
            ]));
        }, $calls);
        return new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $queries);
    }

    /**
     * @param ErisGenerator<M\Call\Clock> $clock Generated constructor operand
     * @param ErisGenerator<M\Literal\Numeral|null>|null $precision Generated constructor operand
     * @param ErisGenerator<M\Expression\OptionalWords>|null $parentheses Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<M\Call\Keyword\ClockAlias|null>|null $alias Generated keyword synonym
     */
    public function __construct(ErisGenerator $clock, ?ErisGenerator $precision = null, ?ErisGenerator $parentheses = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $alias = null)
    {
        $this->delegate = \Tests\Generator\Operands::bind($clock, static function (M\Call\Clock $selected) use ($precision, $parentheses, $scalarInformation, $inputSource, $alias): ErisGenerator {
            return Generators::map(static function (array $parts): M\Call\ClockCall {
                return new M\Call\ClockCall(clock: $parts[0], precision: $parts[1], parentheses: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4], alias: $parts[5]);
            }, \Tests\Generator\Operands::tuple(
                Generators::constant($selected),
                $precision ?? Generators::constant(null),
                $parentheses ?? Generators::constant(M\Expression\OptionalWords::Written),
                $scalarInformation ?? Generators::constant(null),
                $inputSource ?? Generators::constant(null),
                $alias ?? Keyword\ClockAliasGenerator::forClock($selected)
            ));
        });
    }
}
