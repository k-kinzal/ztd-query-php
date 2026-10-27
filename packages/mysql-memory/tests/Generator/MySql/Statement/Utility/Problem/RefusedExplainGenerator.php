<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedExplain values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplain>
 */
final class RefusedExplainGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplain> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain> $explanation Generated constructor operand
     */
    public function __construct(ErisGenerator $explanation)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplain {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplain(explanation: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $explanation
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplain>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Utility\\Problem\\RefusedExplain' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedExplainGenerator(
            explanation: new \Tests\Generator\MySql\Statement\Routine\Program\Server\DeferredExplainGenerator(
                statement: new \Tests\Generator\MySql\Statement\ExplainableGenerator([new \Tests\Generator\MySql\Statement\QueryRootGenerator(
                    query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                        options: Generators::constant([]),
                        items: \Tests\Generator\Operands::tuple(
                            new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                )]),
                                alias: Generators::constant(null),
                                layout: Generators::constant(null),
                                mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                spellingProfile: Generators::constant(null)
                            )])
                        ),
                        from: Generators::constant(null),
                        where: Generators::constant(null),
                        groupBy: Generators::constant(null),
                        having: Generators::constant(null),
                        windows: Generators::constant([]),
                        qualify: Generators::constant(null),
                        orderBy: Generators::constant([]),
                        limit: Generators::constant(null),
                        procedure: Generators::constant(null),
                        locking: Generators::constant([]),
                        into: Generators::constant(null),
                        intoPosition: Generators::constant(null),
                        late: Generators::constant(null),
                        hints: Generators::constant([])
                    )])
                )]),
                format: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('traditional'),
                    quote: Generators::constant(null)
                ),
                into: Generators::constant(null),
                database: Generators::constant(null),
                analyze: Generators::constant(true),
                modifier: Generators::constant(null),
                emptyInto: Generators::constant(false)
            )
        )];
    }

}
