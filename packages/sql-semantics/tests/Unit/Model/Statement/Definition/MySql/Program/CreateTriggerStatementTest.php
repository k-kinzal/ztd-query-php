<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Stored\TriggerOrder;
use SqlSemantics\Model\Definition\Routine\Stored\TriggerOrdering;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateTriggerStatement;
use SqlSemantics\Model\Trigger\Timing;
use SqlSemantics\Model\Trigger\WriteEvent;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateTriggerStatement::class)]
#[Medium]
final class CreateTriggerStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindsTheSameTriggerOnEveryRelease(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (id INT, n INT)'));
        $statement = $binder->bind('CREATE DEFINER = CURRENT_USER TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW BEGIN IF NEW.n < 0 THEN SET NEW.n = OLD.n; END IF; END');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertSame(Timing::Before, $statement->timing);
        self::assertSame(WriteEvent::Update, $statement->event);
        self::assertSame('t', $statement->table->declaration->name);
        self::assertSame('CREATE DEFINER = CURRENT_USER TRIGGER `tr` BEFORE UPDATE ON `t` FOR EACH ROW BEGIN IF(`new`.`n` < 0) THEN SET `new`.`n` = `old`.`n`; END IF; END', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithOriginPreservesTheDefinition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)')))->bind('CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW DO NEW.n');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertSame($statement->toString(), $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithNameReplacesOnlyTheName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)')))->bind('CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW DO NEW.n');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertSame('CREATE TRIGGER `audit` AFTER INSERT ON `t` FOR EACH ROW DO `new`.`n`', $statement->withName(new QualifiedName(['audit']))->toString());
    }

    public function testWithTimingRevalidatesRowAssignments(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)')))->bind('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW DO NEW.n');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertSame('CREATE TRIGGER `tr` AFTER INSERT ON `t` FOR EACH ROW DO `new`.`n`', $statement->withTiming(Timing::After)->toString());
    }

    public function testWithEventRevalidatesRowImages(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)')))->bind('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW DO NEW.n');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertSame(WriteEvent::Update, $statement->withEvent(WriteEvent::Update)->event);
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $statement->withEvent(WriteEvent::Delete);
    }

    public function testWithBodyReplacesTheBody(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)')))->bind('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW DO 1');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertSame('CREATE TRIGGER `tr` BEFORE INSERT ON `t` FOR EACH ROW BEGIN END', $statement->withBody(new BlockStatement(null))->toString());
    }

    public function testWithOrderPlacesTheTrigger(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)')))->bind('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW DO 1');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertSame('CREATE TRIGGER `tr` BEFORE INSERT ON `t` FOR EACH ROW PRECEDES `first` DO 1', $statement->withOrder(new TriggerOrder(TriggerOrdering::Precedes, 'first'))->toString());
    }

    public function testRejectsInsteadOfTiming(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)')))->bind('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW DO 1');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateTriggerStatement($statement->origin, $statement->name, Timing::InsteadOf, $statement->event, $statement->table, $statement->body);
    }

    public function testRejectsAnOrderOnMySql56(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build('CREATE TABLE t (n INT)')))->bind('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW DO 1');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateTriggerStatement($statement->origin, $statement->name, $statement->timing, $statement->event, $statement->table, $statement->body, new TriggerOrder(TriggerOrdering::Follows, 'x'));
    }
}
