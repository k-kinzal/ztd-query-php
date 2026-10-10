<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show;

use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\ShowTableStatusCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(ShowTableStatusCommand::class)]
#[Small]
final class ShowTableStatusCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowTableStatusCommand())->clearsDiagnostics());
    }

    public function testExecuteListsTheTablesOfTheDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY AUTO_INCREMENT, b VARCHAR(10), KEY (b)) COMMENT 'cc'; CREATE TABLE u (a INT)");

        $result = $session->query('SHOW TABLE STATUS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['t', 'u'], array_column($result->rows, 0));
        self::assertSame(['InnoDB', '10', 'Dynamic', '0', '0', '16384', '0', '16384', '0', '1'], array_slice($result->rows[0], 1, 10));
        self::assertSame([null, null, 'utf8mb4_0900_ai_ci', null, '', 'cc'], array_slice($result->rows[0], 12));
    }

    public function testExecuteRefusesAnUnknownDatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'EXECUTE'");

        $session->query("SHOW TABLE STATUS IN EXECUTE LIKE 'text'");
    }

    public function testRowKeepsTheStatisticsItReadFirst(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; SET timestamp=1700000000; CREATE TABLE s (a INT PRIMARY KEY AUTO_INCREMENT, b INT); INSERT INTO s (b) VALUES (1),(2),(3)');
        $table = $session->instance->dictionary->table('d', 's');

        self::assertNotNull($table);
        $first = (new ShowTableStatusCommand())->row($table, new Context($session->modes(), $session->diagnostics, $session->variables, 1700000000.0));
        $session->query('INSERT INTO s (b) VALUES (4)');
        $second = (new ShowTableStatusCommand())->row($table, new Context($session->modes(), $session->diagnostics, $session->variables, 1700000001.0));

        self::assertSame([3, 5461, 16384, 0, 0, 0, 4, '2023-11-14 22:13:20'], array_slice($first, 4, 8));
        self::assertIsString($first[12]);
        self::assertSame($first, $second);
    }

    public function testStatisticsReportsTheNextAutoIncrementValue(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE s (a INT PRIMARY KEY AUTO_INCREMENT)');
        $table = $session->instance->dictionary->table('d', 's');

        self::assertNotNull($table);
        self::assertSame([0, 0, 16384, 0, 0, 0, 1, null], (new ShowTableStatusCommand())->statistics($table, true));
    }

    public function testHeadingsDifferForADerivedTable(): void
    {
        $plain = (new ShowTableStatusCommand())->headings(false);
        $derived = (new ShowTableStatusCommand())->headings(true);

        self::assertSame(['Name', 'Engine', 'Version'], array_map(static fn (Heading $heading): string => $heading->name, array_slice($plain, 0, 3)));
        self::assertSame([Field::Long, Field::LongLong], [$plain[2]->field, $derived[2]->field]);
        self::assertSame([Field::Blob, Field::VarString], [$plain[17]->field, $derived[17]->field]);
    }

    public function testStatisticsCountsThePageOfTheFullTextDocumentIndex(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE t (a INT PRIMARY KEY, b TEXT, FULLTEXT KEY (b))');

        $read1 = $s->query('SHOW TABLE STATUS')[0];
        self::assertInstanceOf(ResultSet::class, $read1);
        self::assertSame('32768', $read1->rows[0][8]);
    }
}
