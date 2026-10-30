<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes RenameTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\RenameTable>
 */
final class RenameTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\RenameTable> */
    use Composition;

    /**
     * Generates complete RenameTable values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\RenameTable>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if (str_starts_with($release->value, 'mysql-')) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No RenameTable forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Alter\RenameTable {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Alter\RenameTable) {
                throw new LogicException('Publishing RenameTable must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Alter\TableRenaming>> $renamings Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\TableWord>|null $tableWord The generated table-list keyword
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\RenameTable>
     */
    public static function operands(ErisGenerator $renamings, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $tableWord = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\RenameTable {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\RenameTable(renamings: $parts[0], statementInformation: $parts[1], inputSource: $parts[2], tableWord: $parts[3]);
        }, Generators::tuple($renamings, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $tableWord ?? new \Tests\Generator\MySql\Statement\Table\TableWordGenerator()));
    }
    /**
     * Generates independent source/destination lists across supported releases.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\RenameTable>>
     */
    public static function forms(): iterable
    {
        $name = new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
            \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::map(static fn (int $id): string => 'name_' . $id, Generators::choose(0, 8)), Generators::constant(null)),
            Generators::oneOf(Generators::constant(null), \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::elements('pbt', 'missing'), Generators::constant(null)))
        );
        $pairs = new TableRenamingGenerator($name, $name);
        yield 'source and destination lists' => self::operands(Generators::bind(Generators::choose(1, 4), static fn (int $count): ErisGenerator => Generators::vector($count, $pairs)));
    }

    /**
     * Composes table creation, optional locking, chained renaming and a return to
     * the original name. Shrinking rebuilds every dependent name and declaration.
     * The second rename consumes the lock retained by the first one.
     *
     * @return ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Statement>>
     */
    public static function withPrerequisites(\SqlSemantics\Contract\GrammarRelease $release, bool $locked = false): ErisGenerator
    {
        $tables = new \Tests\Generator\MySql\Statement\Table\CreateTableGenerator($release, \Tests\Generator\MySql\Statement\Table\CreateTableGenerator::integers());
        $sequence = static function (\SqlSemantics\Platform\MySql\Statement\Table\CreateTable $table, int $count, int $id) use ($release, $locked): ErisGenerator {
            $profile = new \SqlSemantics\Contract\LanguageProfile($release);
            $path = [new \SqlSemantics\Statement\Identifier\Name('pbt')];
            $contexts = Generators::constant(new \SqlSemantics\Contract\AnalysisContext($profile, $path, $table->declarations()));
            $requests = [Generators::constant($table)];
            if ($locked) {
                $aliases = Generators::oneOf(Generators::constant(null), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator());
                $lock = new \Tests\Generator\MySql\Statement\Server\Lock\TableLockGenerator(Generators::constant($table->name), $aliases, \Tests\Generator\MySql\Statement\Server\Lock\LockModeGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Lock\LockMode::Write]));
                $requests[] = new \Tests\Generator\MySql\Statement\Server\Lock\LockTablesGenerator($release, \Tests\Generator\MySql\Statement\Server\Lock\LockTablesGenerator::operands(Generators::tuple($lock)), $contexts);
            }
            $pairs = [];
            $current = $table->name;
            for ($index = 0; $index < $count; ++$index) {
                $next = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('renamed_' . $index . '_' . $id), $table->name->schema);
                $pairs[] = new TableRenamingGenerator(Generators::constant($current), Generators::constant($next));
                $current = $next;
            }
            $requests[] = new self($release, self::operands(Generators::tuple(...$pairs)), $contexts);
            $declaration = $table->declarations()[0];
            $renamed = new \SqlSemantics\Statement\Declaration\Table($current, $declaration->profile, $declaration->columns, $declaration->implicit, $declaration->complete, $declaration->kind, $declaration->keys, $declaration->partitions);
            $after = Generators::constant(new \SqlSemantics\Contract\AnalysisContext($profile, $path, [$renamed]));
            $requests[] = new self($release, self::operands(Generators::tuple(new TableRenamingGenerator(Generators::constant($current), Generators::constant($table->name)))), $after);
            return Generators::tuple(...$requests);
        };
        return Generators::bind(Generators::tuple($tables, Generators::choose(1, 4), Generators::choose(0, 99)), static fn (array $parts): ErisGenerator => $sequence(...$parts));
    }
}
