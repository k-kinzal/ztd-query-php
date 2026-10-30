<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Flush;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes FlushTables values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables>
 */
final class FlushTablesGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables> */
    use Composition;

    /**
     * Generates complete FlushTables values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables>|null $operands A constrained form of this concrete result
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
                throw new InvalidArgumentException('No FlushTables forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables) {
                throw new LogicException('Publishing FlushTables must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\BinlogOption|null> $binlog Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable>>|null $tables Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushLock|null>|null $lock Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\TableWord>|null $tableWord The generated table-list keyword
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables>
     */
    public static function operands(ErisGenerator $binlog, ?ErisGenerator $tables = null, ?ErisGenerator $lock = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $tableWord = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables(binlog: $parts[0], tables: $parts[1], lock: $parts[2], statementInformation: $parts[3], inputSource: $parts[4], tableWord: $parts[5]);
        }, Generators::tuple($binlog, $tables ?? Generators::constant([]), $lock ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $tableWord ?? new \Tests\Generator\MySql\Statement\Table\TableWordGenerator()));
    }
    /**
     * Generates empty and named flushes, preserving the required table list for exports.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables>>
     */
    public static function forms(): iterable
    {
        $name = new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(\Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::map(static fn (int $id): string => 'table_' . $id, Generators::choose(0, 8)), Generators::constant(null)));
        $table = new \Tests\Generator\MySql\Statement\Server\Maintenance\MaintainedTableGenerator($name);
        yield 'table lists and locks' => Generators::bind(Generators::choose(0, 4), static fn (int $count): ErisGenerator => self::operands(
            \Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator::optional(),
            Generators::vector($count, $table),
            $count === 0 ? Generators::elements(null, \SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushLock::WithReadLock) : Generators::oneOf(Generators::constant(null), new FlushLockGenerator()),
        ));
    }

    /**
     * Composes creation with a flush that either acquires read/export locks or uses
     * an existing write lock. Dependent declarations and aliases shrink together.
     *
     * @return ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Statement>>
     */
    public static function withPrerequisites(\SqlSemantics\Contract\GrammarRelease $release, bool $locked): ErisGenerator
    {
        $tables = new \Tests\Generator\MySql\Statement\Table\CreateTableGenerator($release, \Tests\Generator\MySql\Statement\Table\CreateTableGenerator::integers());
        return Generators::bind($tables, static function (\SqlSemantics\Platform\MySql\Statement\Table\CreateTable $create) use ($release, $locked): ErisGenerator {
            $context = Generators::constant(new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new \SqlSemantics\Statement\Identifier\Name('pbt')], $create->declarations()));
            $requests = [Generators::constant($create)];
            if ($locked) {
                $alias = Generators::oneOf(Generators::constant(null), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator());
                $lock = new \Tests\Generator\MySql\Statement\Server\Lock\TableLockGenerator(Generators::constant($create->name), $alias, Generators::constant(\SqlSemantics\Platform\MySql\Statement\Server\Lock\LockMode::Write));
                $requests[] = new \Tests\Generator\MySql\Statement\Server\Lock\LockTablesGenerator($release, \Tests\Generator\MySql\Statement\Server\Lock\LockTablesGenerator::operands(Generators::tuple($lock)), $context);
            }
            $target = new \Tests\Generator\MySql\Statement\Server\Maintenance\MaintainedTableGenerator(Generators::constant($create->name));
            $requests[] = new self($release, self::operands(Generators::constant(null), Generators::tuple($target), $locked ? Generators::constant(null) : new FlushLockGenerator()), $context);
            if ($locked) {
                $requests[] = new self($release, self::operands(Generators::constant(null)), $context);
            }
            $requests[] = new \Tests\Generator\MySql\Statement\Server\Lock\UnlockTablesGenerator($release);
            return Generators::tuple(...$requests);
        });
    }
}
