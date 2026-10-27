<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\View;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CreateView values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\CreateView>
 */
final class CreateViewGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\View\CreateView> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\ViewDefinition> $definition Generated constructor operand
     * @param ErisGenerator<bool>|null $orReplace Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $definition, ?ErisGenerator $orReplace = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\View\CreateView {
            return new \SqlSemantics\Platform\MySql\Statement\View\CreateView(definition: $parts[0], orReplace: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $definition,
            $orReplace ?? Generators::constant(false),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\CreateView>>
     */
    public static function forms(): iterable
    {
        yield 'CreateView/409' => new CreateViewGenerator(
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
            ),
            orReplace: Generators::constant(true)
        );
    }

}
