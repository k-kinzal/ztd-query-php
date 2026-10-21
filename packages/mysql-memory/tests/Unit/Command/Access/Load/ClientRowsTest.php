<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access\Load;

use MySqlMemory\Command\Access\Load\ClientRows;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClientRows::class)]
#[Small]
final class ClientRowsTest extends TestCase
{
    public function testExecuteWritesDecodedRowsAndRetainsTheInputVariables(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT PRIMARY KEY,b VARCHAR(30))');
        $session->running->readFile = static fn (string $file): string => "1,alpha\n2,\\N\n";
        $reply = $session->query("LOAD DATA LOCAL INFILE 'file' INTO TABLE t FIELDS TERMINATED BY ',' (a,@b) SET b=@b")[0];
        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(2, $reply->affectedRows);
        self::assertSame('Records: 2  Deleted: 0  Skipped: 0  Warnings: 0', $reply->info);
        $result = $session->query('SELECT * FROM t ORDER BY a')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', 'alpha'], ['2', null]], $result->rows);
        self::assertNull($session->variables->user('b')[0]);
    }

    public function testValuesKeepsConversionWarningsBeforeMissingFieldWarnings(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT,b VARCHAR(30))');
        $session->running->readFile = static fn (string $file): string => "\n2,a,b\n";
        $reply = $session->query("LOAD DATA LOCAL INFILE 'file' INTO TABLE t FIELDS TERMINATED BY ','")[0];
        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([1366, 1261, 1262], array_column($session->diagnostics->conditions, 1));
        self::assertSame('Records: 2  Deleted: 0  Skipped: 0  Warnings: 3', $reply->info);
        $result = $session->query('SELECT * FROM t ORDER BY a')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', null], ['2', 'a']], $result->rows);
    }

    public function testWidthsUsesIntegerStorageWidthsIndependentlyOfDisplayWidths(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a TINYINT(1),b SMALLINT(2),c MEDIUMINT(3),d INT(4),e BIGINT(5))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        self::assertSame([4, 6, 9, 11, 20], (new ClientRows())->widths([0, 1, 2, 3, 4], $table));
    }

    public function testTargetsPreservesInputOrderAndVariableIdentity(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT,b INT)');
        $operation = $session->analyze("LOAD DATA LOCAL INFILE 'file' INTO TABLE t (b,@v,a)");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable::class, $operation->statement);
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new \MySqlMemory\Plan\Planner($operation->statement, $operation->facts, $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $context), $session->instance->dictionary);
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        self::assertSame([1, $operation->statement->columns[1], 0], (new ClientRows())->targets($operation->statement, $planner, $table));
    }
}
