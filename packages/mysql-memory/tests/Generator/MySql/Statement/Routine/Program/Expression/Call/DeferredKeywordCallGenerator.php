<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Call;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredKeywordCall values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredKeywordCall>
 */
final class DeferredKeywordCallGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredKeywordCall> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\KeywordAlias|null>|null $alias Generated keyword synonym
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Keyword\ArgumentSyntax>|null $syntax Generated argument separators
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction> $function Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand>> $arguments Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $function, ErisGenerator $arguments, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $alias = null, ?ErisGenerator $syntax = null)
    {
        $this->delegate = \Tests\Generator\Operands::bind($function, static function (\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction $selected) use ($arguments, $scalarInformation, $inputSource, $alias, $syntax): ErisGenerator {
            return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredKeywordCall {
                return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredKeywordCall(function: $parts[0], arguments: $parts[1], scalarInformation: $parts[2], inputSource: $parts[3], alias: $parts[4], syntax: $parts[5]);
            }, \Tests\Generator\Operands::tuple(
                Generators::constant($selected),
                $arguments,
                $scalarInformation ?? Generators::constant(null),
                $inputSource ?? Generators::constant(null),
                $alias ?? \Tests\Generator\MySql\Statement\Call\Keyword\KeywordAliasGenerator::forFunction($selected),
                $syntax ?? ($selected === \SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::Substring ? new \Tests\Generator\MySql\Statement\Call\Keyword\ArgumentSyntaxGenerator() : Generators::constant(\SqlSemantics\Platform\MySql\Statement\Call\Keyword\ArgumentSyntax::Commas))
            ));
        });
    }
}
