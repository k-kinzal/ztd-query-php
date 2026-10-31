<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Instance;
use MySqlMemory\Session\Problem\Stages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testInstructionsKeepsSameInstructionNoticesBeforeItsContextualFailure(): void
    {
        $first = new \SqlSemantics\Platform\MySql\Statement\EmptyStatement();
        $second = new \SqlSemantics\Platform\MySql\Statement\EmptyStatement();
        $failure = new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure(new UnknownSystemVariable('missing'), true, \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization);
        $same = new Deprecation(Deprecated::BinaryOperator);
        $later = new Deprecation(Deprecated::BinaryOperator);
        $facts = new \SqlSemantics\Platform\MySql\Statement\StatementSemantics(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), warnings: [new \SqlSemantics\Statement\Fact\Notice($failure, instruction: $first), new \SqlSemantics\Statement\Fact\Notice($same, instruction: $first), new \SqlSemantics\Statement\Fact\Notice($later, instruction: $second)]);

        self::assertSame([1 => $same, 0 => $failure], Stages::instructions([$failure, $same, $later], false, $facts));
        self::assertSame([$failure, $same], Stages::instructions([$failure, $same, $later], true, $facts));
    }

    public function testInstructionsFinishesOrdinaryGrammarNoticesBeforeContextualization(): void
    {
        $session = (new Instance())->connect();
        $session->run('SET NAMES = !1, @after = !2');
        self::assertSame([1287, 1287, 1064], array_column($session->diagnostics->conditions, 1));
        self::assertSame([], $session->variables->user);
    }

    public function testDefinitionConditionKeepsCompleteRequestsSeparateFromInstallableActions(): void
    {
        $name = new QualifiedName(new Name('t'));
        self::assertTrue(Stages::definitionCondition(new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable($name, [], [], null, new \SqlSemantics\Platform\MySql\Statement\Table\TableQuery(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()])))));
        self::assertTrue(Stages::definitionCondition(new \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable($name, [new \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\ValidationOption(true)])));
        self::assertFalse(Stages::definitionCondition(new \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable($name, [new \SqlSemantics\Platform\MySql\Statement\Alter\Command\Force()])));
        self::assertFalse(Stages::definitionCondition(new \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Commit()));
    }

    public function testGrammarLeavesResolutionNoticesForTheirSemanticSubjects(): void
    {
        $resolution = new Deprecation(Deprecated::ValuesElsewhere);
        $syntax = new Deprecation(Deprecated::BinaryOperator);
        self::assertSame([1 => $syntax], Stages::grammar([$resolution, $syntax], false));
        self::assertSame([1 => $syntax], Stages::grammar([$resolution, $syntax], true));
    }

    public function testGrammarRetainsOnlyConditionsFromReachedStages(): void
    {
        $call = new Deprecation(Deprecated::FoundRows);
        $syntax = new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation('missing'));
        $operator = new Deprecation(Deprecated::BangNot);
        $context = new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')), true, \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization);
        $warnings = [$call, $syntax, $operator, $context];

        self::assertSame([1 => $syntax, 2 => $operator], Stages::grammar($warnings, false));
        self::assertSame($warnings, Stages::grammar($warnings, true));
        self::assertSame([1 => $operator, 0 => $call, 2 => $context], Stages::grammar([$call, $operator, $context], false));
    }

    public function testPlannedResolvesConflictingDeclarationsAtTheInstalledTable(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, '8.4.7');
        $context = $semantics->context([$semantics->analyze('CREATE TABLE d.t(a INT)'), $semantics->analyze('CREATE TABLE d.t(b INT)')], true, new \SqlSemantics\Contract\SearchPath('d'));
        $statement = $semantics->analyze('SELECT 1 FROM t', $context);

        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\ConflictingTables::class, $statement->diagnostics()[0]);
        self::assertTrue(Stages::planned($statement->diagnostics()[0]));
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
        $show = $session->analyze('SHOW CREATE TABLE nosuch');
        $select = $session->analyze('SELECT * FROM nosuch');
        $missing = new \SqlSemantics\Statement\Reference\Table\MissingTable(new QualifiedName(new Name('nosuch')));

        self::assertTrue(Stages::answered($show, $missing));
        self::assertFalse(Stages::answered($select, $missing));
    }

    #[DataProvider('providerAnswered')]
    public function testAnsweredLeavesTheMissingTablesOfTheDefinitionAndMaintenanceStatementsToTheirCommands(string $sql, bool $expected): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze($sql);
        $missing = new \SqlSemantics\Statement\Reference\Table\MissingTable(new QualifiedName(new Name('nosuch')));

        self::assertSame($expected, Stages::answered($operation, $missing));
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

    #[DataProvider('providerSelfChecked')]
    public function testSelfCheckedHoldsForTheStatementsWhoseCommandRaisesEveryProblem(string $sql, bool $expected): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze($sql);

        self::assertSame($expected, Stages::selfChecked($operation));
    }

    #[DataProvider('providerOpensTableFirst')]
    public function testOpensTableFirstHoldsForAlterTableCreateIndexAndDropIndex(string $sql, bool $expected): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze($sql);

        self::assertSame($expected, Stages::opensTableFirst($operation));
    }

    #[DataProvider('providerAdministers')]
    public function testAdministersHoldsForTheMaintenanceAndKeyCacheStatements(string $sql, bool $expected): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze($sql);

        self::assertSame($expected, Stages::administers($operation));
    }

    #[DataProvider('providerExplains')]
    public function testExplainsHoldsForExplainAndShowParseTree(string $sql, bool $expected): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze($sql);

        self::assertSame($expected, Stages::explains($operation));
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
        $operation = $session->analyze('CREATE VIEW v AS SELECT 1');
        $statement = $operation;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\View\CreateView::class, $statement);
        $target = new \SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind($statement->definition->name, \SqlSemantics\Platform\MySql\Statement\Table\Problem\KindRefusal::NotView);
        $other = new \SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind(new QualifiedName(new Name('v')), \SqlSemantics\Platform\MySql\Statement\Table\Problem\KindRefusal::NotView);

        self::assertTrue(Stages::viewTarget($statement, $target));
        self::assertTrue(Stages::answered($statement, $target));
        self::assertFalse(Stages::viewTarget($statement, $other));
        $select = $session->analyze('SELECT 1');
        self::assertFalse(Stages::viewTarget($select, $target));
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

    /**
     * @return list<array{string, bool}>
     */
    public static function providerAnswered(): array
    {
        return [
            ['CHECK TABLE nosuch', true],
            ['ALTER TABLE nosuch ADD a INT', true],
            ['RENAME TABLE nosuch TO x', true],
            ['LOCK TABLES nosuch READ', true],
            ['LOAD DATA INFILE \'x\' INTO TABLE nosuch', true],
            ['TRUNCATE TABLE nosuch', false],
        ];
    }

    /**
     * @return list<array{string, bool}>
     */
    public static function providerSelfChecked(): array
    {
        return [
            ['CREATE USER u IDENTIFIED BY \'x\'', true],
            ['INSTALL COMPONENT \'file://x\'', true],
            ['FLUSH TABLES', true],
            ['SELECT 1', false],
        ];
    }

    /**
     * @return list<array{string, bool}>
     */
    public static function providerOpensTableFirst(): array
    {
        return [
            ['ALTER TABLE t ADD a INT', true],
            ['CREATE INDEX i ON t (a)', true],
            ['DROP INDEX i ON t', true],
            ['DROP TABLE t', false],
        ];
    }

    /**
     * @return list<array{string, bool}>
     */
    public static function providerAdministers(): array
    {
        return [
            ['CHECKSUM TABLE t', true],
            ['CACHE INDEX t IN hot', true],
            ['OPTIMIZE TABLE t', true],
            ['TRUNCATE TABLE t', false],
        ];
    }

    /**
     * @return list<array{string, bool}>
     */
    public static function providerExplains(): array
    {
        return [
            ['EXPLAIN SELECT 1', true],
            ['EXPLAIN FOR CONNECTION 1', true],
            ['SELECT 1', false],
        ];
    }


    public function testLockedDistinguishesLockTargetsFromQueryAliases(): void
    {
        self::assertTrue(Stages::locked(new Misuse(MisuseRule::UnknownLockedTable, new Name('t'))));
        self::assertTrue(Stages::locked(new Misuse(MisuseRule::RepeatedLockedTable, new Name('t'))));
        self::assertFalse(Stages::locked(new Misuse(MisuseRule::DuplicateAlias, new Name('t'))));
    }

    public function testDuplicateAliasIncludesTheInsertRowNamespace(): void
    {
        $session = (new Instance())->connect();
        $insert = $session->analyze('INSERT INTO t VALUES (1) AS t');
        $query = $session->analyze('SELECT 1');
        $alias = new Misuse(MisuseRule::DuplicateAlias, new Name('t'));
        self::assertTrue(Stages::duplicateAlias($alias, $insert));
        self::assertFalse(Stages::duplicateAlias($alias, $query));
        self::assertTrue(Stages::duplicateAlias(new \SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable(new Name('t')), $query));
        self::assertFalse(Stages::duplicateAlias(new Misuse(MisuseRule::UnknownLockedTable, new Name('t')), $insert));
    }

}
