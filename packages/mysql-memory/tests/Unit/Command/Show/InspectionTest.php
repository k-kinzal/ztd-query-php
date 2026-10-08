<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show;

use MySqlMemory\Command\Show\Inspection;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Inspection::class)]
#[Small]
final class InspectionTest extends TestCase
{
    public function testCheckFindsAMissingTableBeforeTheWhereClause(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);
        $this->expectExceptionMessage("Table 'd.nosuch' doesn't exist");

        $session->query('SHOW COLUMNS FROM nosuch WHERE nocol = 1');
    }

    public function testCheckFindsAMissingDatabaseOfShowTableStatus(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'nodb'");

        $session->query('SHOW TABLE STATUS FROM nodb WHERE nocol = 1');
    }

    public function testInspectsAnswersWhetherAStatementReportsOnATable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        self::assertTrue((new Inspection())->inspects($session->analyze('SHOW CREATE TABLE t')->statement));
        self::assertFalse((new Inspection())->inspects($session->analyze('SELECT 1')->statement));
    }

    public function testWarnWarnsOfAPatternThatIsNoUtf8mb4String(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('DESC PERSIST._sqlfaker_identifier 0b01000011101101001010110010000000')->statement;
        (new Inspection())->warn($statement, $session);

        self::assertSame(1, $session->diagnostics->count());
    }

    public function testWarnWarnsBeforeTheMissingDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->run('DESC PERSIST._sqlfaker_identifier 0b01000011101101001010110010000000');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1300', "Invalid utf8mb4 character string: 'B4AC80'"], ['Error', '1049', "Unknown database 'PERSIST'"]], $warnings->rows);
    }

    public function testPatternReadsHexadecimalAndBitLiterals(): void
    {
        self::assertSame(['C', 'C', 'a_'], [
            (new Inspection())->pattern(new Text('43', EscapeRule::Backslash, Radix::Hexadecimal)),
            (new Inspection())->pattern(new Text('1000011', EscapeRule::Backslash, Radix::Bit)),
            (new Inspection())->pattern(new Text('a_')),
        ]);
    }

    public function testDatabaseRefusesWithoutACurrentDatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);

        (new Inspection())->database(null, $session);
    }

    public function testDatabaseAnswersTheDatabaseItNames(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');

        self::assertSame('d', (new Inspection())->database(new Name('d'), $session)->name);
    }

    public function testTableReplacesTheDatabaseWrittenWithTheName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);
        $this->expectExceptionMessage("Table 'd.t' doesn't exist");

        (new Inspection())->table(new InspectedTable(new QualifiedName(new Name('t'), new Name('nodb'))), new Name('d'), $session);
    }

    public function testTableDescribesAViewAsATableOfItsColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT NOT NULL DEFAULT 5); CREATE VIEW v AS SELECT a, a + 1 AS c FROM t');

        $stored = (new Inspection())->table(new InspectedTable(new QualifiedName(new Name('v'))), null, $session);

        self::assertSame(['a', 'c', 5, 0], [$stored->definition->columns[0]->name, $stored->definition->columns[1]->name, $stored->definition->columns[0]->default->value, $stored->definition->columns[1]->default->value]);
    }

    public function testTableRefusesAMissingDatabaseFirst(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'nodb'");

        (new Inspection())->table(new InspectedTable(new QualifiedName(new Name('t'), new Name('nodb'))), null, $session);
    }

    public function testCheckRefusesTheMissingDatabaseOfShowTablesBeforeItsCondition(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'nodb'");

        $session->query('SHOW TABLES IN nodb WHERE zz');
    }

    public function testTableReportsATableOfAMissingDatabaseAsMissingIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Table 'nodb.t' doesn't exist");

        $session->query('SHOW COLUMNS FROM nodb.t');
    }

    public function testSystemFindsTheSystemTableAStatementInspects(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE t (a INT)');

        self::assertSame(['SCHEMATA', null, null], [(new Inspection())->system(new InspectedTable(new QualifiedName(new Name('schemata'), new Name('INFORMATION_SCHEMA'))), null, $s)?->name, (new Inspection())->system(new InspectedTable(new QualifiedName(new Name('t'))), null, $s), (new Inspection())->system(new InspectedTable(new QualifiedName(new Name('user'))), new Name('nowhere'), $s)]);
    }
}
