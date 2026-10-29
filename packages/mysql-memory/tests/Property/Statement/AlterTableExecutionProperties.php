<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use Tests\Eris\TestTrait;
use Tests\Generator\MySql\Statement\Alter\AlterTableGenerator;

/**
 * Executes generated alterations together with their table and row dependencies.
 */
#[CoversNothing]
#[Large]
final class AlterTableExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Rename connectors preserve generated rows and either TRUNCATE form removes them.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesRenameAndTruncateConsumeDependentValues(GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Alter\TruncateTableGenerator::renamedTables($release))->then(
            /** @param array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement} $inputs */
            static function (array $inputs) use ($release): void {
                [$create, $insert, $before, $rename, $after, $truncate] = $inputs;
                self::assertInstanceOf(CreateTable::class, $create);
                self::assertInstanceOf(InsertRows::class, $insert);
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $before);
                self::assertInstanceOf(AlterTable::class, $rename);
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $after);
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable::class, $truncate);
                $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
                $session->text = 'generated rename and truncate without SQL';
                $execution = new Execution($session);
                self::assertInstanceOf(Completion::class, $execution->statement($create));
                self::assertInstanceOf(Completion::class, $execution->statement($insert));
                $original = $execution->statement($before);
                self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $original);
                self::assertCount(1, $original->rows);
                self::assertInstanceOf(Completion::class, $execution->statement($rename));
                $renamed = $execution->statement($after);
                self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $renamed);
                self::assertSame($original->rows, $renamed->rows);
                self::assertNull($session->instance->dictionary->schema('pbt')?->table('type_pair'));
                self::assertInstanceOf(Completion::class, $execution->statement($truncate));
                $empty = $execution->statement($after);
                self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $empty);
                self::assertSame([], $empty->rows);
                self::assertSame('generated rename and truncate without SQL', $session->text);
                self::assertSame([], $session->transaction->statements->running);
            }
        );
    }

    /**
     * Dependent generated statements retain rows and the completion expected for their engine.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesIndexChangesPreserveRowsAndReportEngineCopies(GrammarRelease $release): void
    {
        $this->forAll(AlterTableGenerator::indexSequences($release))->then(/** @param array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, int} $sequence */
            static function (array $sequence) use ($release): void {
                [$create, $insert, $alter, $affected] = $sequence;
                self::assertInstanceOf(CreateTable::class, $create);
                self::assertInstanceOf(InsertRows::class, $insert);
                self::assertInstanceOf(AlterTable::class, $alter);
                $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
                $session->text = 'Generated index change without SQL';
                $execution = new Execution($session);
                $execution->statement($create);
                $execution->statement($insert);
                $reply = $execution->statement($alter);
                self::assertInstanceOf(Completion::class, $reply);
                self::assertSame($affected, $reply->affectedRows);
                self::assertSame('Records: ' . $reply->affectedRows . '  Duplicates: 0  Warnings: 0', $reply->info);
                self::assertSame(0, $reply->warnings);
                $table = $session->instance->dictionary->table('pbt', $create->name->name->value, $session->temporaries);
                self::assertNotNull($table);
                $expected = [];
                foreach ($insert->rows as $row) {
                    $values = [];
                    foreach ($row->values as $value) {
                        self::assertInstanceOf(StringLiteral::class, $value);
                        $values[] = (int) $value->value();
                    }
                    $expected[] = $values;
                }
                self::assertSame($expected, array_values($table->data->rows));
                self::assertSame('Generated index change without SQL', $session->text);
            }
        );
    }

    /**
     * Functional-index slots follow generated definitions through additions and replacements.
     */
    #[Test]
    #[DataProvider('providerFunctionalReleases')]
    public function satisfiesFunctionalIndexChangesPreserveRowsAndCountVirtualCopies(GrammarRelease $release): void
    {
        $this->forAll(AlterTableGenerator::functionalSequences($release))->then(static function (array $sequence) use ($release): void {
            [$create, $insert, $alter, $affected] = $sequence;
            self::assertInstanceOf(CreateTable::class, $create);
            self::assertInstanceOf(InsertRows::class, $insert);
            self::assertInstanceOf(AlterTable::class, $alter);
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated functional index without SQL';
            $execution = new Execution($session);
            $execution->statement($create);
            $execution->statement($insert);
            $reply = $execution->statement($alter);
            self::assertInstanceOf(Completion::class, $reply);
            self::assertSame($affected, $reply->affectedRows);
            self::assertSame('Records: ' . $reply->affectedRows . '  Duplicates: 0  Warnings: 0', $reply->info);
            self::assertSame(0, $reply->warnings);
            $table = $session->instance->dictionary->table('pbt', $create->name->name->value);
            self::assertNotNull($table);
            $expected = [];
            foreach ($insert->rows as $row) {
                self::assertInstanceOf(StringLiteral::class, $row->values[0]);
                $expected[] = (int) $row->values[0]->value();
            }
            self::assertSame($expected, array_column($table->data->rows, 0));
            self::assertSame('Generated functional index without SQL', $session->text);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerFunctionalReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql' && !in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                yield $release->value => [$release];
            }
        }
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }
}
