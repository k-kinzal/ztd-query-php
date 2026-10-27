<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\View;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AlterView values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\AlterView>
 */
final class AlterViewGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\View\AlterView> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\ViewDefinition> $definition Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     */
    public function __construct(ErisGenerator $definition, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $relationInformation = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\View\AlterView {
            return new \SqlSemantics\Platform\MySql\Statement\View\AlterView(definition: $parts[0], statementInformation: $parts[1], inputSource: $parts[2], relationInformation: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $definition,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $relationInformation ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\AlterView>>
     */
    public static function forms(): iterable
    {
        yield 'AlterView/13' => new AlterViewGenerator(
            definition: new ViewDefinitionGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )]),
                columns: Generators::constant(null),
                algorithm: Generators::constant(null),
                definer: Generators::constant(null),
                security: Generators::constant(null),
                check: Generators::constant(null),
                ifNotExists: Generators::constant(false)
            )
        );
        yield 'AlterView/246' => new AlterViewGenerator(
            definition: new ViewDefinitionGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )]),
                columns: Generators::constant(null),
                algorithm: ViewAlgorithmGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\View\ViewAlgorithm::Undefined]),
                definer: Generators::constant(null),
                security: Generators::constant(null),
                check: Generators::constant(null),
                ifNotExists: Generators::constant(false)
            )
        );
        yield 'AlterView/410' => new AlterViewGenerator(
            definition: new ViewDefinitionGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )]),
                columns: Generators::constant(null),
                algorithm: ViewAlgorithmGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\View\ViewAlgorithm::Undefined]),
                definer: Generators::constant(null),
                security: ViewSecurityGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\View\ViewSecurity::Definer]),
                check: Generators::constant(null),
                ifNotExists: Generators::constant(false)
            )
        );
        yield 'AlterView/411' => new AlterViewGenerator(
            definition: new ViewDefinitionGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                ),
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator(
                    table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )]),
                columns: Generators::constant(null),
                algorithm: ViewAlgorithmGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\View\ViewAlgorithm::Undefined]),
                definer: Generators::constant(null),
                security: Generators::constant(null),
                check: ViewCheckOptionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\View\ViewCheckOption::Unqualified]),
                ifNotExists: Generators::constant(false)
            )
        );
    }

}
