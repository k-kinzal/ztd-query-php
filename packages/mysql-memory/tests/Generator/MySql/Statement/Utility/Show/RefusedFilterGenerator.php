<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedFilter values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\RefusedFilter>
 */
final class RefusedFilterGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\RefusedFilter> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTableStatus|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowEvents|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionStatus|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowDatabases|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowStatus|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowVariables|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCharacterSet|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCollation> $header Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar> $condition Generated constructor operand
     */
    public function __construct(ErisGenerator $header, ErisGenerator $condition)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\RefusedFilter {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\RefusedFilter(header: $parts[0], condition: $parts[1]);
        }, Generators::tuple(
            $header,
            $condition
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\RefusedFilter>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Utility\\Show\\RefusedFilter' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedFilterGenerator(
            header: Schema\ShowTableStatusGenerator::operands(
                database: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 'd' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                filter: Generators::constant(null)
            ),
            condition: new \Tests\Generator\MySql\Statement\Expression\Problem\RefusedScalarGenerator(
                operand: new \Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator([new \Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredLikeGenerator(
                    operand: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                        elements: Generators::tuple(
                            new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )]),
                            new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )])
                        ),
                        keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                    )]),
                    pattern: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                    )]),
                    escape: Generators::constant(null),
                    negated: Generators::constant(false)
                )])
            )
        )];
    }

}
