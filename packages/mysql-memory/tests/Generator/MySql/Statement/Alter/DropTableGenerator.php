<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes DropTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\DropTable>
 */
final class DropTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\DropTable> */
    use Composition;

    /**
     * Generates complete DropTable values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\DropTable>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $names = \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::map(static fn (int $id): string => 't_' . $id, Generators::choose(0, 3)), Generators::constant(null));
            $schemas = Generators::oneOf(Generators::constant(null), \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::elements('pbt', 'missing'), Generators::constant(null)));
            $target = new TargetTableGenerator(new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator($names, $schemas));
            $tables = Generators::bind(Generators::choose(1, 6), static fn (int $count): ErisGenerator => Generators::vector($count, $target));
            $forms = [self::operands(Generators::bool(), Generators::bool(), $tables, Generators::oneOf(Generators::constant(null), new DropBehaviorGenerator()))];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                $forms[] = AbsentTableGenerator::statements();
            }

            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Alter\DropTable {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Alter\DropTable) {
                throw new LogicException('Publishing DropTable must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<bool> $temporary Generated constructor operand
     * @param ErisGenerator<bool> $ifExists Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Alter\TargetTable>> $tables Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\DropBehavior|null>|null $behavior Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\TableWord>|null $tableWord The generated table-list keyword
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\DropTable>
     */
    public static function operands(ErisGenerator $temporary, ErisGenerator $ifExists, ErisGenerator $tables, ?ErisGenerator $behavior = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $tableWord = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\DropTable {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\DropTable(temporary: $parts[0], ifExists: $parts[1], tables: $parts[2], behavior: $parts[3], statementInformation: $parts[4], inputSource: $parts[5], tableWord: $parts[6]);
        }, Generators::tuple($temporary, $ifExists, $tables, $behavior ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $tableWord ?? new \Tests\Generator\MySql\Statement\Table\TableWordGenerator()));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\DropTable>>
     */
    public static function forms(): iterable
    {
        yield 'DropTable/41' => DropTableGenerator::operands(
            temporary: Generators::constant(false),
            ifExists: Generators::constant(false),
            tables: Generators::tuple(
                new TargetTableGenerator(
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
            behavior: Generators::constant(null)
        );
        yield 'DropTable/276' => DropTableGenerator::operands(
            temporary: Generators::constant(false),
            ifExists: Generators::constant(false),
            tables: Generators::tuple(
                new TargetTableGenerator(
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
            behavior: DropBehaviorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Alter\DropBehavior::Restrict])
        );
    }

}
