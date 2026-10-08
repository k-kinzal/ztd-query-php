<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Maintenance;

use MySqlMemory\Command\Maintenance\Histograms;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\DropHistogram;

#[CoversClass(Histograms::class)]
#[Small]
final class HistogramsTest extends TestCase
{
    public function testCheckRefusesAColumnNamedTwice(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('ANALYZE TABLE t UPDATE HISTOGRAM ON e, E')->statement;
        self::assertInstanceOf(AnalyzeTable::class, $statement);
        self::assertNotNull($statement->histogram);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1060);
        $this->expectExceptionMessage("Duplicate column name 'E'");

        (new Histograms())->check($statement->histogram);
    }

    public function testRowsCreatesHistogramsInTheBinaryOrderOfTheColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY, b JSON, e INT, f INT, Y INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $statement = $session->analyze('ANALYZE TABLE t UPDATE HISTOGRAM ON zz, f, y, b, e')->statement;
        self::assertNotNull($table);
        self::assertInstanceOf(AnalyzeTable::class, $statement);
        self::assertNotNull($statement->histogram);

        $rows = (new Histograms())->rows($statement->histogram, $table);

        self::assertSame([
            ['status', "Histogram statistics created for column 'Y'."],
            ['Error', "The column 'b' has an unsupported data type."],
            ['status', "Histogram statistics created for column 'e'."],
            ['status', "Histogram statistics created for column 'f'."],
            ['Error', "The column 'zz' does not exist."],
        ], $rows);
        self::assertSame(['y' => 'Y', 'e' => 'e', 'f' => 'f'], $table->histograms);
    }

    public function testRowsRefusesATemporaryTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TEMPORARY TABLE t (a INT)');
        $statement = $session->analyze('ANALYZE TABLE t DROP HISTOGRAM ON a')->statement;
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        self::assertInstanceOf(AnalyzeTable::class, $statement);
        self::assertNotNull($statement->histogram);

        self::assertSame([['Error', 'Cannot create histogram statistics for a temporary table.']], (new Histograms())->rows($statement->histogram, $table));
    }

    public function testRowsRefusesJsonDataForSeveralColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT)');
        $statement = $session->analyze("ANALYZE TABLE t UPDATE HISTOGRAM ON a, b USING DATA '{}'")->statement;
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        self::assertInstanceOf(AnalyzeTable::class, $statement);
        self::assertNotNull($statement->histogram);

        self::assertSame([['Error', 'Only one column can be specified while modifying histogram statistics with JSON data.']], (new Histograms())->rows($statement->histogram, $table));
    }

    public function testDropRemovesTheHistogramsThatExist(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, Y INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $statement = $session->analyze('ANALYZE TABLE t DROP HISTOGRAM ON y, a')->statement;
        self::assertNotNull($table);
        self::assertInstanceOf(AnalyzeTable::class, $statement);
        self::assertInstanceOf(DropHistogram::class, $statement->histogram);
        $table->histograms = ['y' => 'Y'];

        self::assertSame([['Error', "No histogram statistics found for column 'a'."], ['status', "Histogram statistics removed for column 'y'."]], (new Histograms())->drop($statement->histogram->columns, $table));
        self::assertSame([], $table->histograms);
    }

    public function testProblemAnswersASinglePartUniqueIndex(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, v VARCHAR(5), UNIQUE KEY (v(2)))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame("The column 'v' is covered by a single-part unique index.", (new Histograms())->problem($table, 'v', 1));
    }

    public function testLoadReportsInvalidJsonData(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([
            ['Error', 'Invalid JSON text in argument 1 to function UPDATE HISTOGRAM: "Invalid value." at position 0.'],
            ['Error', "Unable to build histogram statistics for column 'a' in table 'd'.'t'"],
            ['Error', 'JSON format error.'],
        ], (new Histograms())->load('x', 'a', $table));
        self::assertSame([
            ["Unable to build histogram statistics for column 'a' in table 'd'.'t'", 'JSON data is not an object'],
            ["Unable to build histogram statistics for column 'a' in table 'd'.'t'", "Missing attribute at '$.\"data-type\"'."],
        ], [array_column((new Histograms())->load('[]', 'a', $table), 1), array_column((new Histograms())->load('{"histogram-type": "singleton"}', 'a', $table), 1)]);
    }
}
