<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem\Operand;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedQuery values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery>
 */
final class RefusedQueryGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery> $query Generated constructor operand
     */
    public function __construct(ErisGenerator $query)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery(query: $parts[0]);
        }, Generators::tuple(
            $query
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Query\\Problem\\Operand\\RefusedQuery' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedQueryGenerator(
            query: new \Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredQueryGenerator(
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\QueryExpressionGenerator(
                    with: new \Tests\Generator\MySql\Statement\Query\With\WithGenerator(
                        recursive: Generators::constant(true),
                        tables: Generators::tuple(
                            new \Tests\Generator\MySql\Statement\Query\With\CommonTableExpressionGenerator(
                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                    value: Generators::constant('c'),
                                    quote: Generators::constant(null)
                                ),
                                columns: Generators::constant([]),
                                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                                    options: Generators::constant([]),
                                    items: Generators::tuple(
                                        new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator(
                                            expression: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                                                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                                    value: Generators::constant('x'),
                                                    quote: Generators::constant(null)
                                                ),
                                                qualifier: Generators::constant(null),
                                                dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                            )]),
                                            alias: Generators::constant(null),
                                            layout: Generators::constant(null),
                                            mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                            spellingProfile: Generators::constant(null)
                                        )])
                                    ),
                                    from: new \Tests\Generator\MySql\Statement\Relation\TableSourceGenerator([new \Tests\Generator\MySql\Statement\Relation\TableReferenceGenerator(
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
                                        indexHints: Generators::constant([]),
                                        sample: Generators::constant(null),
                                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                                    )]),
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
                    body: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
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
                        from: new \Tests\Generator\MySql\Statement\Relation\TableSourceGenerator([new \Tests\Generator\MySql\Statement\Relation\TableReferenceGenerator(
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
                            indexHints: Generators::constant([]),
                            sample: Generators::constant(null),
                            mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As]),
                            dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                        )]),
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
                    )]),
                    orderBy: Generators::constant([]),
                    limit: Generators::constant(null)
                )])
            )
        )];
    }

}
