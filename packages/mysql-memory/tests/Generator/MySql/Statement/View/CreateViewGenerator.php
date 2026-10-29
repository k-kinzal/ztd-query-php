<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\View;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
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
     * Generates complete CreateView values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\CreateView>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No CreateView forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\View\CreateView {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\View\CreateView) {
                throw new LogicException('Publishing CreateView must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\ViewDefinition> $definition Generated constructor operand
     * @param ErisGenerator<bool>|null $orReplace Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\CreateView>
     */
    public static function operands(ErisGenerator $definition, ?ErisGenerator $orReplace = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\View\CreateView {
            return new \SqlSemantics\Platform\MySql\Statement\View\CreateView(definition: $parts[0], orReplace: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple($definition, $orReplace ?? Generators::constant(false), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\CreateView>>
     */
    public static function forms(): iterable
    {
        yield 'CreateView/tableless-star' => CreateViewGenerator::operands(
            definition: new ViewDefinitionGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'view_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    )
                ),
                query: new \Tests\Generator\Semantics\Statement\QueryGenerator([new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                    options: Generators::constant([]),
                    items: Generators::tuple(new \Tests\Generator\MySql\Statement\Query\StarGenerator())
                )]),
                algorithm: ViewAlgorithmGenerator::selecting(\SqlSemantics\Platform\MySql\Statement\View\ViewAlgorithm::cases()),
                ifNotExists: Generators::constant(false)
            )
        );
        yield 'CreateView/409' => CreateViewGenerator::operands(
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
