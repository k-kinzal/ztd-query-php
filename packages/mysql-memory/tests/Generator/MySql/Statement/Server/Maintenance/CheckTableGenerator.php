<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Maintenance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes CheckTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable>
 */
final class CheckTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable> */
    use Composition;

    /**
     * Generates complete CheckTable values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $legacy = in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
            $name = \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::map(static fn (int $id): string => 't_' . $id, Generators::choose(0, 8)), Generators::constant(null));
            $table = new MaintainedTableGenerator(new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator($name), dot: $legacy ? new \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator() : Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted));
            $forms = [self::operands(Generators::bind(Generators::choose(1, 4), static fn (int $count): ErisGenerator => Generators::vector($count, $table)))];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable) {
                throw new LogicException('Publishing CheckTable must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable>> $tables Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\CheckOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\TableWord>|null $tableWord The generated table-list keyword
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable>
     */
    public static function operands(ErisGenerator $tables, ?ErisGenerator $options = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $tableWord = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable(tables: $parts[0], options: $parts[1], statementInformation: $parts[2], inputSource: $parts[3], tableWord: $parts[4]);
        }, Generators::tuple($tables, $options ?? Generators::constant([]), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $tableWord ?? new \Tests\Generator\MySql\Statement\Table\TableWordGenerator()));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable>>
     */
    public static function forms(): iterable
    {
        yield 'CheckTable/18' => CheckTableGenerator::operands(
            tables: Generators::tuple(
                new MaintainedTableGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )
            ),
            options: Generators::constant([])
        );
        yield 'CheckTable/289' => CheckTableGenerator::operands(
            tables: Generators::tuple(
                new MaintainedTableGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    )
                )
            ),
            options: Generators::tuple(
                \Tests\Generator\MySql\Statement\Server\CheckOptionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\CheckOption::Quick])
            )
        );
    }

}
