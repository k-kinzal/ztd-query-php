<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(CreateTrigger::class)]
#[Medium]
final class CreateTriggerTest extends TestCase
{
    public function testDeriveStatementResolvesTheNewRowAndLocalVariablesInEveryBodyStatement(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = [$semantics->analyze('CREATE TABLE t (a INT, b INT)'), $semantics->analyze('CREATE TABLE u (c INT)')];
        $create = $semantics->analyze('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW BEGIN DECLARE v INT DEFAULT NEW.a; '
            . 'SET NEW.b = v + NEW.a; UPDATE u SET c = NEW.a WHERE c = v; INSERT INTO u (c) VALUES (NEW.b); END', $context);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTrigger::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);

        self::assertSame([], $create->facts->diagnostics);
    }

    public function testDeriveStatementResolvesBothRowsOfAnUpdateTriggerWhateverTheirCase(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');

        self::assertSame([], $semantics->analyze('CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET new.a = old.b + NEW.b', [$table])->facts->diagnostics);
    }

    public function testDeriveStatementResolvesTheOldRowOfADeleteTrigger(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = [$semantics->analyze('CREATE TABLE t (a INT, b INT)'), $semantics->analyze('CREATE TABLE u (c INT)')];

        self::assertSame([], $semantics->analyze('CREATE TRIGGER tr AFTER DELETE ON t FOR EACH ROW DELETE FROM u WHERE c = OLD.a', $context)->facts->diagnostics);
    }

    public function testDeriveStatementReportsAColumnTheRowLacks(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $create = $semantics->analyze('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.zz = 1', [$table]);

        self::assertSame(['Column NEW.zz does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    public function testDeriveStatementReportsATableTheContextLacks(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $create = $semantics->analyze('CREATE TRIGGER tr BEFORE INSERT ON u FOR EACH ROW SET @x = 1', [$table]);

        self::assertSame(['Relation u does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, string, ProgramRule, string}>
     */
    public static function providerDeriveStatementReportsAnAssignmentToARowItMayNotChange(): iterable
    {
        yield 'OLD row' => ['BEFORE UPDATE', 'OLD', ProgramRule::OldRowUpdate, 'Updating of OLD row is not allowed in trigger'];
        yield 'OLD row of an insert' => ['BEFORE INSERT', 'old', ProgramRule::OldRowUpdate, 'Updating of OLD row is not allowed in trigger'];
        yield 'NEW row after the change' => ['AFTER UPDATE', 'NEW', ProgramRule::AfterRowUpdate, 'Updating of NEW row is not allowed in after trigger'];
        yield 'NEW row of a delete' => ['BEFORE DELETE', 'NEW', ProgramRule::NoNewRow, 'There is no NEW row in on DELETE trigger'];
    }

    #[DataProvider('providerDeriveStatementReportsAnAssignmentToARowItMayNotChange')]
    public function testDeriveStatementReportsAnAssignmentToARowItMayNotChange(string $timing, string $row, ProgramRule $rule, string $message): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $create = $semantics->analyze('CREATE TRIGGER tr ' . $timing . ' ON t FOR EACH ROW SET ' . $row . '.a = 1', [$table]);
        $problem = $create->facts->diagnostics[0] ?? null;
        self::assertInstanceOf(ProgramProblem::class, $problem);

        self::assertCount(1, $create->facts->diagnostics);
        self::assertSame($rule, $problem->rule);
        self::assertSame($message, $problem->message());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDeriveStatementReportsAStatementATriggerMayNotContain(): iterable
    {
        yield 'result set' => ['SELECT 1', 'Not allowed to return a result set from a trigger'];
        yield 'COMMIT' => ['COMMIT', 'Explicit or implicit commit is not allowed in stored function or trigger.'];
        yield 'FLUSH' => ['FLUSH TABLES', 'FLUSH is not allowed in stored function or trigger'];
        yield 'PREPARE' => ['PREPARE s FROM @q', 'Dynamic SQL is not allowed in stored function or trigger'];
        yield 'RETURN' => ['RETURN 1', 'RETURN is only allowed in a FUNCTION'];
        yield 'LOCK TABLES' => ['LOCK TABLES t READ', 'LOCK is not allowed in stored procedures'];
        yield 'CREATE TRIGGER' => ['CREATE TRIGGER other AFTER INSERT ON t FOR EACH ROW SET @a = 1', "Can't create a TRIGGER from within another stored routine"];
    }

    #[DataProvider('providerDeriveStatementReportsAStatementATriggerMayNotContain')]
    public function testDeriveStatementReportsAStatementATriggerMayNotContain(string $body, string $message): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $create = $semantics->analyze('CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW ' . $body, [$table]);

        self::assertSame([$message], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheDefinition(): iterable
    {
        yield 'MySQL 5.6 definer and qualified names' => [
            'mysql-5.6.51',
            'create definer = current_user trigger shop.tr after insert on shop.t for each row set @a = 1',
            'CREATE DEFINER = CURRENT_USER TRIGGER shop.tr AFTER INSERT ON shop.t FOR EACH ROW SET @a = 1',
        ];
        yield 'MySQL 5.7 order' => [
            'mysql-5.7.44',
            'create trigger tr before update on t for each row follows other set new.a = old.a',
            'CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW FOLLOWS other SET `new`.a = old.a',
        ];
        yield 'MySQL 8.0 IF NOT EXISTS' => [
            'mysql-8.0.44',
            'create trigger if not exists tr after delete on t for each row precedes o begin delete from u where c = old.a; end',
            'CREATE TRIGGER IF NOT EXISTS tr AFTER DELETE ON t FOR EACH ROW PRECEDES o BEGIN DELETE FROM u WHERE c = `old`.a; END',
        ];
        yield 'MySQL 9.1 program body' => [
            'mysql-9.1.0',
            'create trigger tr before insert on t for each row begin declare v int default new.a; set new.b = v; end',
            'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW BEGIN DECLARE v INT DEFAULT `new`.a; SET `new`.b = v; END',
        ];
    }

    #[DataProvider('providerRenderWritesTheDefinition')]
    public function testRenderWritesTheDefinition(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    public function testANameWithACatalogIsRejected(): void
    {
        $this->expectExceptionMessage('A trigger name has at most a database qualifier.');

        new CreateTrigger(new QualifiedName(new Name('tr'), new Name('shop'), new Name('def')), TriggerTime::Before, TriggerEvent::Insert, new TriggerTable(new QualifiedName(new Name('t'))), new Block([]));
    }

    public function testABodyOfAnotherClassIsRejected(): void
    {
        $this->expectExceptionMessage('A member of a stored program is a program statement or an SQL statement.');

        new CreateTrigger(new QualifiedName(new Name('tr')), TriggerTime::Before, TriggerEvent::Insert, new TriggerTable(new QualifiedName(new Name('t'))), new Parameter(new Name('x'), new Integral(IntegralKind::Int)));
    }
}
