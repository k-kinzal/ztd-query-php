<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
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
        $created = new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, new self(Generators::bool(), $names));
        return \Tests\Generator\Operands::bind($created, static function (\SqlSemantics\Statement\AnalyzedStatement $create) use ($release): ErisGenerator {
            \SqlSemantics\Diagnostic\Check::invariant($create instanceof \SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase, 'Dependent database requests retain their creation.');
            $name = Generators::constant($create->name);
            $collations = new DatabaseCollationGenerator(new \Tests\Generator\MySql\Statement\Name\CollationNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::elements(['utf8mb4_bin', 'utf8mb4_unicode_ci']))));
            return \Tests\Generator\Operands::tuple(
                Generators::constant($create),
                new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, new \Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowCreateDatabaseGenerator($name, Generators::bool())),
                new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, new AlterDatabaseGenerator($name, \Tests\Generator\Operands::vector(1, $collations))),
                new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, new DropDatabaseGenerator(Generators::bool(), $name)),
            );
        });
    }

    /**
     * @param ErisGenerator<bool> $ifNotExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseWord>|null $databaseWord Generated database keyword
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $ifNotExists, ErisGenerator $name, ?ErisGenerator $options = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $databaseWord = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase(ifNotExists: $parts[0], name: $parts[1], options: $parts[2], statementInformation: $parts[3], inputSource: $parts[4], databaseWord: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $ifNotExists,
            $name,
            $options ?? Generators::constant([]),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $databaseWord ?? new DatabaseWordGenerator()
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase>>
     */
    public static function forms(): iterable
    {
        yield 'CreateDatabase/22' => new CreateDatabaseGenerator(
            ifNotExists: Generators::constant(false),
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            options: Generators::constant([])
        );
    }

}
