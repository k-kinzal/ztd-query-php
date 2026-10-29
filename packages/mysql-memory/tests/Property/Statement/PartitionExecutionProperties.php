<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Statement\AnalyzedStatement;
use Tests\Eris\TestTrait;
use Tests\Generator\MySql\Statement\Partition\Method\HashMethodGenerator;

/**
 * Executes generated partition operations with their declarations and session restrictions.
 */
#[CoversNothing]
#[Large]
final class PartitionExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Dependent partition selection retains rows and reports the native version's copy count.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesRebuildPreservesGeneratedRowsAndReportsSelectedCopies(GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Alter\Partition\MaintainPartitionsGenerator::withRows($release))->then(/** @param array{AnalyzedStatement, AnalyzedStatement, AnalyzedStatement, list<list<int>>, int} $sample */
            static function (array $sample) use ($release): void {
                [$create, $insert, $alter, $rows, $affected] = $sample;
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $create);
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows::class, $insert);
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable::class, $alter);
                $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
                $session->text = 'Generated partition rebuild without SQL';
                $execution = new Execution($session);
                $execution->statement($create);
                $execution->statement($insert);
                $reply = $execution->statement($alter);
                self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
                self::assertSame($affected, $reply->affectedRows);
                self::assertSame('Records: ' . $reply->affectedRows . '  Duplicates: 0  Warnings: 0', $reply->info);
                self::assertSame(0, $reply->warnings);
                $table = $session->instance->dictionary->table('pbt', $create->name->name->value);
                self::assertNotNull($table);
                self::assertSame($rows, array_values($table->data->rows));
                self::assertSame('Generated partition rebuild without SQL', $session->text);
            }
        );
    }

    /**
     * Session-dependent methods refuse both preparation and execution without SQL input.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesGeneratedSessionDependenceFailsBeforeTableLookup(GrammarRelease $release): void
    {
        $this->forAll(HashMethodGenerator::refusedRequests($release))->then(static function (AnalyzedStatement $statement) use ($release): void {
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated partition request without SQL';
            try {
                (new \MySqlMemory\Session\Access\Preparation())->check($statement, $session);
                self::fail('A session-dependent partition method cannot be prepared.');
            } catch (SqlError $error) {
                self::assertSame(1064, $error->getCode());
            }
            try {
                (new Execution($session))->statement($statement);
                self::fail('A session-dependent partition method cannot execute.');
            } catch (SqlError $error) {
                self::assertSame(1064, $error->getCode());
            }
            self::assertSame('Generated partition request without SQL', $session->text);
            self::assertSame([], $session->transaction->statements->running);
        });
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
