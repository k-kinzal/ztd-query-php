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
    public function testGrammarRetainsOnlyConditionsFromReachedStages(): void
    {
        $call = new Deprecation(Deprecated::FoundRows);
        $syntax = new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation('missing'));
        $operator = new Deprecation(Deprecated::BangNot);
        $context = new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')), true, \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization);
        $warnings = [$call, $syntax, $operator, $context];

        self::assertSame([1 => $syntax, 2 => $operator], Stages::grammar($warnings, false));
        self::assertSame($warnings, Stages::grammar($warnings, true));
        self::assertSame([$call, $operator, $context], Stages::grammar([$call, $operator, $context], false));
    }

    public function testPlannedLeavesWindowOptionsAndFramesToThePlanner(): void
    {
        self::assertTrue(Stages::planned(new UnsupportedWindowing(WindowingLimit::IgnoreNulls)));
        self::assertTrue(Stages::planned(new UnsupportedWindowing(WindowingLimit::FromLast)));
        self::assertTrue(Stages::planned(new UnsupportedWindowing(WindowingLimit::Exclusion)));
        self::assertTrue(Stages::planned(new UnsupportedWindowing(WindowingLimit::GroupConcat)));
        self::assertTrue(Stages::planned(new UnsupportedWindowing(WindowingLimit::DistinctAggregate)));
        self::assertFalse(Stages::planned(new Misuse(MisuseRule::DuplicateWindow, new Name('w'))));
    }

    public function testPlannedPreservesRangePreparationBeforeTheBoundaryKindCondition(): void
    {
        $start = new \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::Following, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'));
        $end = new \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::CurrentRow);
        $rows = new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec(frame: new \SqlSemantics\Platform\MySql\Statement\Call\Window\Frame(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Rows, $start, $end));
        $range = new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec(frame: new \SqlSemantics\Platform\MySql\Statement\Call\Window\Frame(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Range, $start, $end));
        self::assertFalse(Stages::planned(new \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowConstraint($rows, false)));
        self::assertTrue(Stages::planned(new \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowConstraint($range, false)));
    }

    public function testPlannedLeavesOwnedAggregateDiagnosticsToTheirBlockLocation(): void
    {
        $call = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregate(new \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')]), new Name('w'));

        self::assertFalse(Stages::planned(new UnsupportedWindowing(WindowingLimit::GroupConcat, $call)));
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
        self::assertTrue(Stages::parsed(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet('LIMIT & IN/ALL/ANY/SOME subquery')));
        self::assertTrue(Stages::parsed(new \SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem(\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule::IdentifierOutOfRange)));
        self::assertTrue(Stages::parsed(new \SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem(\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule::IdentifierOutOfRange, \SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind::Organization)));
        self::assertTrue(Stages::parsed(new \SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1.0'))));
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

    public function testTableDefinitionLeavesTheMissingColumnsOfCreateTableToItsCommand(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1824);
        $this->expectExceptionMessage("Failed to open the referenced table 'nope'");

        $session->query('CREATE TABLE c (a INT, FOREIGN KEY (a) REFERENCES nope(id))');
    }
    public function testViewTargetRetainsOnlyTheOwningDefinitionKindCheck(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('CREATE VIEW v AS SELECT 1')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\View\CreateView::class, $statement);
        $target = new \SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind($statement->definition->name, \SqlSemantics\Platform\MySql\Statement\Table\Problem\KindRefusal::NotView);
        $other = new \SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind(new QualifiedName(new Name('v')), \SqlSemantics\Platform\MySql\Statement\Table\Problem\KindRefusal::NotView);

        self::assertTrue(Stages::viewTarget($statement, $target));
        self::assertTrue(Stages::answered($statement, $target));
        self::assertFalse(Stages::viewTarget($statement, $other));
        self::assertFalse(Stages::viewTarget($session->analyze('SELECT 1')->statement, $target));
    }

    public function testParsedRetainsRoutineIdentityConditionsBeforeOpeningTables(): void
    {
        self::assertTrue(Stages::parsed(new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::IncorrectName, '')));
        self::assertTrue(Stages::parsed(new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::NameTooLong, str_repeat('f', 65))));
    }

    public function testCharactersDistinguishesGrammarCollationsFromLegacyResolution(): void
    {
        $collation = new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation('missing');
        self::assertTrue(Stages::characters($collation, false));
        self::assertFalse(Stages::characters($collation, true));
        self::assertFalse(Stages::characters(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation(''), false));
        self::assertTrue(Stages::characters(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset('missing'), true));
    }

}
