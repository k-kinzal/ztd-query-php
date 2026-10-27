<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem\Delete;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedDelete values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Delete\RefusedDelete>
 */
final class RefusedDeleteGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Delete\RefusedDelete> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredDelete|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete> $write Generated constructor operand
     */
    public function __construct(ErisGenerator $write)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Delete\RefusedDelete {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Delete\RefusedDelete(write: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $write
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Delete\RefusedDelete>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Problem\\Delete\\RefusedDelete' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedDeleteGenerator(
            write: new \Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredDeleteGenerator(
                with: new \Tests\Generator\MySql\Statement\Query\With\WithGenerator(
                    recursive: Generators::constant(false),
                    tables: \Tests\Generator\Operands::tuple(
                        new \Tests\Generator\MySql\Statement\Query\With\CommonTableExpressionGenerator(
                            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::constant('c'),
                                quote: Generators::constant(null)
                            ),
                            columns: Generators::constant([]),
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
                        )
                    )
                ),
                options: Generators::constant([]),
                table: new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('c'),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    ),
                    alias: Generators::constant(null),
                    partitions: Generators::constant([]),
                    mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                ),
                where: Generators::constant(null),
                orderBy: Generators::constant([]),
                limit: Generators::constant(null),
                hints: Generators::constant([])
            )
        )];
    }

}
