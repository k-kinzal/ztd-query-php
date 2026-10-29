<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem\Operand;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedSetClause values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSetClause>
 */
final class RefusedSetClauseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSetClause> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Select|\SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody> $operand Generated constructor operand
     */
    public function __construct(ErisGenerator $operand)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSetClause {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSetClause(operand: $parts[0]);
        }, Generators::tuple(
            $operand
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSetClause>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Query\\Problem\\Operand\\RefusedSetClause' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, new RefusedSetClauseGenerator(
            operand: new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                options: Generators::constant([]),
                items: Generators::tuple(
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
                orderBy: Generators::tuple(
                    new \Tests\Generator\MySql\Statement\Query\OrderItemGenerator(
                        expression: new \Tests\Generator\MySql\Statement\Query\Clause\OutputOrdinalGenerator(
                            expression: new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                            )
                        ),
                        direction: Generators::constant(null)
                    )
                ),
                limit: Generators::constant(null),
                procedure: Generators::constant(null),
                locking: Generators::constant([]),
                into: Generators::constant(null),
                intoPosition: Generators::constant(null),
                late: Generators::constant(null),
                hints: Generators::constant([])
            )
        )];
    }

}
