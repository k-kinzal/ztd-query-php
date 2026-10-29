<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Composition;

/**
 * Composes TruncateTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable>
 */
final class TruncateTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable> */
    use Composition;

    /**
     * Generates complete TruncateTable values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<M\Alter\TruncateTable>|null $operands A constrained form of this concrete result
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
                throw new InvalidArgumentException('No TruncateTable forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): M\Alter\TruncateTable {
            if (!$statement instanceof M\Alter\TruncateTable) {
                throw new LogicException('Publishing TruncateTable must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<QualifiedName> $table Generated constructor operand
     * @param ErisGenerator<M\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<M\Expression\OptionalWords>|null $tableWord Generated written alternative
     * @return ErisGenerator<M\Alter\TruncateTable>
     */
    public static function operands(ErisGenerator $table, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $relationInformation = null, ?ErisGenerator $tableWord = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): M\Alter\TruncateTable {
            return new M\Alter\TruncateTable(table: $parts[0], statementInformation: $parts[1], inputSource: $parts[2], relationInformation: $parts[3], tableWord: $parts[4]);
        }, Generators::tuple($table, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $relationInformation ?? Generators::constant(null), $tableWord ?? new \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator()));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<M\Alter\TruncateTable>>
     */
    public static function forms(): iterable
    {
        yield 'TruncateTable/125' => TruncateTableGenerator::operands(
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            )
        );
    }

    /**
     * Composes writes, a rename and truncation with the declarations needed at each step.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement}>
     */
    public static function renamedTables(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        return Generators::bind(\Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator::synonymWrites($release), static function (array $inputs) use ($release): ErisGenerator {
            [$create, $insert, $before] = $inputs;
            \SqlSemantics\Diagnostic\Check::invariant($create instanceof M\Table\CreateTable, 'The dependent rename starts with a table definition.');
            \SqlSemantics\Diagnostic\Check::invariant($insert instanceof M\Dml\Insert\InsertRows && $before instanceof M\QueryRoot, 'The generated rows and read use that definition.');
            $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, $release->value);
            $search = new \SqlSemantics\Contract\SearchPath('pbt');
            $original = $semantics->context($create->declarations(), true, $search);
            $name = new QualifiedName(new Name('renamed_pair'));
            $renamed = \SqlSemantics\Facade\Semantics::fromStatement($semantics->context([], true, $search), new M\Table\CreateTable($name, $create->elements));
            $context = $semantics->context($renamed->declarations(), true, $search);
            $actions = new Command\RenameToGenerator(Generators::constant($name));
            $changes = Generators::map(static fn (M\Alter\Command\RenameTo $action): M\Alter\AlterTable => new M\Alter\AlterTable($create->name, [$action]), $actions);
            $after = new M\QueryRoot(new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference($name)));
            return Generators::tuple(
                Generators::constant($create),
                Generators::constant($insert),
                Generators::constant($before),
                new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $changes, Generators::constant($original)),
                new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, Generators::constant($after), Generators::constant($context)),
                new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, self::operands(Generators::constant($name)), Generators::constant($context)),
            );
        });
    }

}
