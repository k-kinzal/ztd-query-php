<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Routine\Stored\TriggerDefinitions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\EmbeddedStatement;
use SqlSemantics\Model\Scalar\Reference\TriggerColumn;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateTriggerStatement;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TriggerDefinitions::class)]
#[Medium]
final class TriggerDefinitionsTest extends TestCase
{
    public function testBindResolvesRowImagesInOrdinaryStatements(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT, n INT); CREATE TABLE audit (id INT, n INT)'));
        $statement = $binder->bind('CREATE TRIGGER tr AFTER UPDATE ON t FOR EACH ROW UPDATE audit SET n = NEW.n WHERE id = OLD.id');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertInstanceOf(EmbeddedStatement::class, $statement->body);
        self::assertInstanceOf(UpdateTableStatement::class, $statement->body->statement);
        $columns = array_values(array_filter(\SqlSemantics\Model\Traversal\Expressions::all($statement), static fn ($expression): bool => $expression instanceof TriggerColumn));
        self::assertEqualsCanonicalizing([RowVersion::New, RowVersion::Old], array_map(static fn (TriggerColumn $column): RowVersion => $column->version, $columns));
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testBindDiagnosesTablesInAnotherDatabase(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramDefinition->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TRIGGER a.tr BEFORE INSERT ON b.t FOR EACH ROW DO 1', strict: false);
    }

    public function testBindDiagnosesAnOldRowInAnInsertTrigger(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramObject->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)')))->bind('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW DO OLD.n', strict: false);
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindReadsEveryTriggerForm')]
    public function testBindReadsEveryTriggerForm(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, $statement->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindReadsEveryTriggerForm(): iterable
    {
        return [
            'create trigger tr before insert on t for each row set new.a = 1 (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'create trigger tr before insert on t for each row set new.a = 1', 'CREATE TRIGGER `tr` BEFORE INSERT ON `t` FOR EACH ROW SET `new`.`a` = 1'],
            'create trigger tr after delete on t for each row set @x = old.a (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'create trigger tr after delete on t for each row set @x = old.a', 'CREATE TRIGGER `tr` AFTER DELETE ON `t` FOR EACH ROW SET @`x` = `old`.`a`'],
            'create trigger tr before update on t for each row set new.a = old.a (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'create trigger tr before update on t for each row set new.a = old.a', 'CREATE TRIGGER `tr` BEFORE UPDATE ON `t` FOR EACH ROW SET `new`.`a` = `old`.`a`'],
            'CREATE TRIGGER d.tr BEFORE INSERT ON d.t FOR EACH ROW SET @x = 1 (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'CREATE TRIGGER d.tr BEFORE INSERT ON d.t FOR EACH ROW SET @x = 1', 'CREATE TRIGGER `d`.`tr` BEFORE INSERT ON `d`.`t` FOR EACH ROW SET @`x` = 1'],
            'CREATE TRIGGER d.tr BEFORE INSERT ON t FOR EACH ROW SET @x = 1 (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'CREATE TRIGGER d.tr BEFORE INSERT ON t FOR EACH ROW SET @x = 1', 'CREATE TRIGGER `d`.`tr` BEFORE INSERT ON `t` FOR EACH ROW SET @`x` = 1'],
            'CREATE TRIGGER tr BEFORE INSERT ON d.t FOR EACH ROW SET @x = 1 (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'CREATE TRIGGER tr BEFORE INSERT ON d.t FOR EACH ROW SET @x = 1', 'CREATE TRIGGER `tr` BEFORE INSERT ON `d`.`t` FOR EACH ROW SET @`x` = 1'],
            'create trigger tr after insert on t for each row follows other set @x = 1 (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'create trigger tr after insert on t for each row follows other set @x = 1', 'CREATE TRIGGER `tr` AFTER INSERT ON `t` FOR EACH ROW FOLLOWS `other` SET @`x` = 1'],
            'create trigger tr after insert on t for each row precedes `Other` set @x = 1 (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'create trigger tr after insert on t for each row precedes `Other` set @x = 1', 'CREATE TRIGGER `tr` AFTER INSERT ON `t` FOR EACH ROW PRECEDES `Other` SET @`x` = 1'],
            'create trigger if not exists tr after insert on t for each row set @x = 1 (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'create trigger if not exists tr after insert on t for each row set @x = 1', 'CREATE TRIGGER IF NOT EXISTS `tr` AFTER INSERT ON `t` FOR EACH ROW SET @`x` = 1'],
            'create definer = current_user trigger tr after insert on t for each row set @x = 1 (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'create definer = current_user trigger tr after insert on t for each row set @x = 1', 'CREATE DEFINER = CURRENT_USER TRIGGER `tr` AFTER INSERT ON `t` FOR EACH ROW SET @`x` = 1'],
        ];
    }

    #[TestWith(['CREATE TRIGGER d.tr BEFORE INSERT ON e.t FOR EACH ROW SET @x = 1'])]
    #[TestWith(['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET @x = old.a'])]
    #[TestWith(['CREATE TRIGGER tr BEFORE DELETE ON t FOR EACH ROW SET @x = new.a'])]
    public function testBindRejectsAnotherDatabaseOrAnAbsentRow(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT)'));
        $this->expectException(InvalidSql::class);
        $binder->bind($sql, strict: false);
    }
}
