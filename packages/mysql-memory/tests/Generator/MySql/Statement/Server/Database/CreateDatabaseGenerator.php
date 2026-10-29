<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes CreateDatabase values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase>
 */
final class CreateDatabaseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase> */
    use Composition;

    /**
     * Composes database creation, inspection, alteration and removal with one generated name.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement}>
     */
    public static function lifecycle(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $names = new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::map(static fn (int $value): string => 'database_' . $value, Generators::choose(0, 10000)));
        $created = new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, self::operands(Generators::bool(), $names));
        return Generators::bind($created, static function (\SqlSemantics\Statement\AnalyzedStatement $create) use ($release): ErisGenerator {
            \SqlSemantics\Diagnostic\Check::invariant($create instanceof \SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase, 'Dependent database requests retain their creation.');
            $name = Generators::constant($create->name);
            $collations = new DatabaseCollationGenerator(new \Tests\Generator\MySql\Statement\Name\CollationNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::elements(['utf8mb4_bin', 'utf8mb4_unicode_ci']))));
            return Generators::tuple(
                Generators::constant($create),
                new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, \Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowCreateDatabaseGenerator::operands($name, Generators::bool())),
                new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, AlterDatabaseGenerator::operands($name, Generators::vector(1, $collations))),
                new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, DropDatabaseGenerator::operands(Generators::bool(), $name)),
            );
        });
    }

    /**
     * Generates complete CreateDatabase values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase>|null $operands A constrained form of this concrete result
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
                throw new InvalidArgumentException('No CreateDatabase forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase) {
                throw new LogicException('Publishing CreateDatabase must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<bool> $ifNotExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseWord>|null $databaseWord Generated database keyword
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase>
     */
    public static function operands(ErisGenerator $ifNotExists, ErisGenerator $name, ?ErisGenerator $options = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $databaseWord = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase(ifNotExists: $parts[0], name: $parts[1], options: $parts[2], statementInformation: $parts[3], inputSource: $parts[4], databaseWord: $parts[5]);
        }, Generators::tuple($ifNotExists, $name, $options ?? Generators::constant([]), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $databaseWord ?? new DatabaseWordGenerator()));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase>>
     */
    public static function forms(): iterable
    {
        yield 'CreateDatabase/22' => CreateDatabaseGenerator::operands(
            ifNotExists: Generators::constant(false),
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            options: Generators::constant([])
        );
    }

}
