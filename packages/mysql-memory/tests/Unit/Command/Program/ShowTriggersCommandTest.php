<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Program\ShowTriggersCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(ShowTriggersCommand::class)]
#[Small]
final class ShowTriggersCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowTriggersCommand())->clearsDiagnostics());
    }

    public function testExecuteListsByTableEventTimeAndOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE TABLE b (a INT)');
        $session->query('CREATE TRIGGER z1 BEFORE INSERT ON t FOR EACH ROW SET @a = 1');
        $session->query('CREATE TRIGGER a2 BEFORE INSERT ON t FOR EACH ROW PRECEDES z1 SET @a = 2');
        $session->query('CREATE TRIGGER m3 AFTER DELETE ON b FOR EACH ROW SET @a = 3');
        $session->query('CREATE TRIGGER b5 AFTER UPDATE ON t FOR EACH ROW SET @a = 5');
        $session->query('CREATE TRIGGER b6 BEFORE UPDATE ON t FOR EACH ROW SET @a = 6');

        $result = $session->query('SHOW TRIGGERS')[0];
        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame(['m3', 'a2', 'z1', 'b6', 'b5'], array_column($result->rows, 0));
        self::assertSame(['SET @a = 3', 'BEFORE'], [$result->rows[0][3], $result->rows[1][4]]);
    }

    public function testExecuteMatchesLikeAgainstTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE TRIGGER z1 BEFORE INSERT ON t FOR EACH ROW SET @a = 1');

        $result1 = $session->query("SHOW TRIGGERS LIKE 'z%'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        $result2 = $session->query("SHOW TRIGGERS LIKE 't'")[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([[], ['z1']], [$result1->rows, array_column($result2->rows, 0)]);
    }

    public function testExecuteRefusesAMissingDatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'SHUTDOWN'");

        $session->query("SHOW FULL TRIGGERS FROM SHUTDOWN LIKE 'x'");
    }

    public function testHeadingsRetainDirectTimestampConversionAndBaseIndexes(): void
    {
        $command = new ShowTriggersCommand();
        $materialized = $command->headings(GrammarRelease::MySql847);
        $direct = $command->headings(GrammarRelease::MySql847, true);

        self::assertSame(22, $materialized[5]->column(Charset::known('utf8mb4'))->length);
        self::assertSame(88, $direct[5]->column(Charset::known('utf8mb4'))->length);
        self::assertSame([20617, 'trg', 'information_schema'], [$direct[7]->flags, $direct[7]->originalTable, $direct[7]->schema]);
        self::assertSame(20485, $direct[8]->flags);
    }

    public function testLegacyRetainsNullableCreationTimeAndVersionedWidths(): void
    {
        $oldest = (new ShowTriggersCommand())->legacy(GrammarRelease::MySql5651);
        $later = (new ShowTriggersCommand())->headings(GrammarRelease::MySql5744, true);

        self::assertSame([Field::DateTime, 19, 128, 0], [$oldest[5]->field, $oldest[5]->length, $oldest[5]->flags, $oldest[5]->decimals]);
        self::assertSame([22, 2], [$later[5]->length, $later[5]->decimals]);
        self::assertSame([77, 93], [$oldest[7]->length, $later[7]->length]);
        self::assertSame('EVENT_MANIPULATION', $oldest[1]->originalName);
    }
}
