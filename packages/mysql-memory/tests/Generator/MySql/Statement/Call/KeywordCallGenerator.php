<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
use Tests\Generator\Composition;
use Tests\Generator\MySql\Statement\Call\Keyword\ArgumentSyntaxGenerator;
use Tests\Generator\MySql\Statement\Call\Keyword\KeywordAliasGenerator;

/**
 * Composes KeywordCall values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\KeywordCall>
 */
final class KeywordCallGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\KeywordCall> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\KeywordAlias|null>|null $alias Generated keyword synonym
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\ArgumentSyntax>|null $syntax Generated argument separators
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction> $function Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Scalar>> $arguments Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords>|null $parentheses Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $function, ErisGenerator $arguments, ?ErisGenerator $parentheses = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $alias = null, ?ErisGenerator $syntax = null)
    {
        $this->delegate = Generators::bind($function, static function (\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction $selected) use ($arguments, $parentheses, $scalarInformation, $inputSource, $alias, $syntax): ErisGenerator {
            return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall {
                return new \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall(function: $parts[0], arguments: $parts[1], parentheses: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4], alias: $parts[5], syntax: $parts[6]);
            }, Generators::tuple(
                Generators::constant($selected),
                $arguments,
                $parentheses ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written),
                $scalarInformation ?? Generators::constant(null),
                $inputSource ?? Generators::constant(null),
                $alias ?? KeywordAliasGenerator::forFunction($selected),
                $syntax ?? ($selected === \SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::Substring ? new ArgumentSyntaxGenerator() : Generators::constant(\SqlSemantics\Platform\MySql\Statement\Call\Keyword\ArgumentSyntax::Commas))
            ));
        });
    }

    /**
     * Composes projections of a synonym and its canonical function with shared operands.
     *
     * @return ErisGenerator<\SqlSemantics\Statement\AnalyzedStatement>
     */
    public static function queries(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $calls = Generators::bind(new KeywordAliasGenerator(), static function (\SqlSemantics\Platform\MySql\Statement\Call\Keyword\KeywordAlias $alias): ErisGenerator {
            $arguments = $alias->function() === \SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::Substring
                ? Generators::oneOf(...[
                    Generators::tuple(new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator(), new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 5)))),
                    Generators::tuple(new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator(), new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 5))), new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 5)))),
                ]) : (in_array($alias->function(), [\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::Year, \SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::Week], true) ? Generators::tuple(new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator(strings: Generators::map(static fn (int $year): string => (string) $year . '-01-01', Generators::choose(2000, 2100)))) : Generators::constant([]));
            return new self(Generators::constant($alias->function()), $arguments, alias: Generators::constant($alias));
        });
        $queries = Generators::map(static function (\SqlSemantics\Platform\MySql\Statement\Call\KeywordCall $call): \SqlSemantics\Platform\MySql\Statement\QueryRoot {
            $canonical = new \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall($call->function, array_map(static fn (\SqlSemantics\Statement\Scalar $literal): \SqlSemantics\Statement\Scalar => match (true) {
                $literal instanceof \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral => new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral([$literal->value()]),
                $literal instanceof \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral => new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral($literal->text),
                default => throw new LogicException('Generated synonym comparisons use literal operands.'),
            }, $call->arguments));
            return new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [
                new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($call, new \SqlSemantics\Statement\Identifier\Name('selected')),
                new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($canonical, new \SqlSemantics\Statement\Identifier\Name('canonical')),
            ]));
        }, $calls);
        return new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $queries);
    }
}
