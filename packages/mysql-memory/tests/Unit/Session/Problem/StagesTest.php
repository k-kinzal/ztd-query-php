<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Instance;
use MySqlMemory\Session\Problem\Stages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Stages::class)]
#[Small]
final class StagesTest extends TestCase
{
    public function testPlannedLeavesWindowOptionsToThePlannerButKeepsFrameRefusals(): void
    {
        self::assertTrue(Stages::planned(new UnsupportedWindowing(WindowingLimit::IgnoreNulls)));
        self::assertTrue(Stages::planned(new UnsupportedWindowing(WindowingLimit::FromLast)));
        self::assertFalse(Stages::planned(new UnsupportedWindowing(WindowingLimit::Exclusion)));
        self::assertFalse(Stages::planned(new Misuse(MisuseRule::DuplicateWindow, new Name('w'))));
    }

    public function testAnsweredLeavesTheMissingTableOfAShowStatementToItsCommand(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $show = $session->analyze('SHOW CREATE TABLE nosuch')->statement;
        $select = $session->analyze('SELECT * FROM nosuch')->statement;
        $missing = new \SqlSemantics\Statement\Reference\Table\MissingTable(new QualifiedName(new Name('nosuch')));

        self::assertTrue(Stages::answered($show, $missing));
        self::assertFalse(Stages::answered($select, $missing));
    }

    public function testAnsweredLeavesTheMissingTablesOfTheDefinitionAndMaintenanceStatementsToTheirCommands(): void
    {
        $session = (new Instance())->connect();
        $missing = new \SqlSemantics\Statement\Reference\Table\MissingTable(new QualifiedName(new Name('nosuch')));

        self::assertSame([true, true, true, true, true, false], [
            Stages::answered($session->analyze('CHECK TABLE nosuch')->statement, $missing),
            Stages::answered($session->analyze('ALTER TABLE nosuch ADD a INT')->statement, $missing),
            Stages::answered($session->analyze('RENAME TABLE nosuch TO x')->statement, $missing),
            Stages::answered($session->analyze('LOCK TABLES nosuch READ')->statement, $missing),
            Stages::answered($session->analyze("LOAD DATA INFILE 'x' INTO TABLE nosuch")->statement, $missing),
            Stages::answered($session->analyze('TRUNCATE TABLE nosuch')->statement, $missing),
        ]);
    }

    public function testParsedHoldsForTheProblemsOfTheParser(): void
    {
        self::assertTrue(Stages::parsed(new WrongArgumentCount(new Name('abs'), 0)));
        self::assertFalse(Stages::parsed(new Misuse(MisuseRule::UnknownLockedTable, new QualifiedName(new Name('t')))));
        self::assertFalse(Stages::parsed(new Misuse(MisuseRule::DuplicateWindow, new Name('w'))));
    }

    public function testLateHoldsForAWindowDefinedTwice(): void
    {
        self::assertTrue(Stages::late(new Misuse(MisuseRule::DuplicateWindow, new Name('w'))));
        self::assertFalse(Stages::late(new Misuse(MisuseRule::UnknownWindow, new Name('w'))));
    }

    public function testClosingHoldsForTheProblemsAtTheEndOfAQueryBlock(): void
    {
        self::assertTrue(Stages::closing(new Misuse(MisuseRule::UnknownLockedTable, new QualifiedName(new Name('t')))));
        self::assertTrue(Stages::closing(new Misuse(MisuseRule::RepeatedLockedTable, new Name('t'))));
        self::assertTrue(Stages::closing(new UndeclaredVariable(new Name('n'))));
        self::assertFalse(Stages::closing(new WrongArgumentCount(new Name('abs'), 0)));
    }

    public function testAfterReadingHoldsForTheDeprecationOfIntoInsideAQuery(): void
    {
        self::assertTrue(Stages::afterReading(new Deprecation(Deprecated::IntoInsideQuery)));
        self::assertFalse(Stages::afterReading(new Deprecation(Deprecated::BinaryOperator)));
    }

    public function testSelfCheckedHoldsForTheStatementsWhoseCommandRaisesEveryProblem(): void
    {
        $session = (new Instance())->connect();

        self::assertSame([true, true, true, false], [Stages::selfChecked($session->analyze("CREATE USER u IDENTIFIED BY 'x'")->statement), Stages::selfChecked($session->analyze("INSTALL COMPONENT 'file://x'")->statement), Stages::selfChecked($session->analyze('FLUSH TABLES')->statement), Stages::selfChecked($session->analyze('SELECT 1')->statement)]);
    }

    public function testOpensTableFirstHoldsForAlterTableCreateIndexAndDropIndex(): void
    {
        $session = (new Instance())->connect();

        self::assertSame([true, true, true, false], [Stages::opensTableFirst($session->analyze('ALTER TABLE t ADD a INT')->statement), Stages::opensTableFirst($session->analyze('CREATE INDEX i ON t (a)')->statement), Stages::opensTableFirst($session->analyze('DROP INDEX i ON t')->statement), Stages::opensTableFirst($session->analyze('DROP TABLE t')->statement)]);
    }

    public function testAdministersHoldsForTheMaintenanceAndKeyCacheStatements(): void
    {
        $session = (new Instance())->connect();

        self::assertSame([true, true, true, false], [Stages::administers($session->analyze('CHECKSUM TABLE t')->statement), Stages::administers($session->analyze('CACHE INDEX t IN hot')->statement), Stages::administers($session->analyze('OPTIMIZE TABLE t')->statement), Stages::administers($session->analyze('TRUNCATE TABLE t')->statement)]);
    }

    public function testExplainsHoldsForExplainAndShowParseTree(): void
    {
        $session = (new Instance())->connect();

        self::assertSame([true, true, false], [Stages::explains($session->analyze('EXPLAIN SELECT 1')->statement), Stages::explains($session->analyze('EXPLAIN FOR CONNECTION 1')->statement), Stages::explains($session->analyze('SELECT 1')->statement)]);
    }

    public function testParsedHoldsForASystemVariableTheServerDoesNotKnow(): void
    {
        self::assertTrue(Stages::parsed(new UnknownSystemVariable('nosuch')));
    }

    public function testAnsweredLeavesTheMissingColumnsOfCreateTableToItsCommand(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1824);
        $this->expectExceptionMessage("Failed to open the referenced table 'nope'");

        $session->query('CREATE TABLE c (a INT, FOREIGN KEY (a) REFERENCES nope(id))');
    }
}
