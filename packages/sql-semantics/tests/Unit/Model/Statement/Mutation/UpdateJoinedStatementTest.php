<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\Joining\OnJoin;
use SqlSemantics\Model\Statement\Mutation\UpdateJoinedStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UpdateJoinedStatement::class)]
#[Medium]
final class UpdateJoinedStatementTest extends TestCase
{
    public function testBindsTheJoinedInputAndModifiers(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)', 'CREATE TABLE s(id INT, n INT)')))->bind('UPDATE LOW_PRIORITY IGNORE t JOIN s ON t.id=s.id SET t.n=s.n WHERE t.id>0');
        self::assertInstanceOf(UpdateJoinedStatement::class, $statement);
        self::assertInstanceOf(OnJoin::class, $statement->from);
        self::assertTrue($statement->lowPriority);
        self::assertTrue($statement->ignore);
        self::assertSame(StatementKind::Update, $statement->kind);
        self::assertSame([], $statement->resultColumns());
        self::assertSame('UPDATE LOW_PRIORITY IGNORE `t` INNER JOIN `s` ON (`t`.`id` = `s`.`id`) SET `t`.`n` = `s`.`n` WHERE (`t`.`id` > 0)', $statement->toString());
    }

    public function testAffectedTablesListsOnlyTheAssignedTargets(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)', 'CREATE TABLE s(id INT, n INT)')))->bind('UPDATE t, s SET t.n=s.n WHERE t.id=s.id');
        self::assertInstanceOf(UpdateJoinedStatement::class, $statement);
        self::assertSame($statement->targets, $statement->affectedTables());
        self::assertSame(['t'], array_map(static fn ($table): string => $table->declaration->name, $statement->affectedTables()));
        self::assertSame('UPDATE `t` CROSS JOIN `s` SET `t`.`n` = `s`.`n` WHERE (`t`.`id` = `s`.`id`)', $statement->toString());
    }

    public function testWithOriginPreservesTargetsAndTheJoin(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)', 'CREATE TABLE s(id INT, n INT)')))->bind('UPDATE t JOIN s ON t.id=s.id SET t.n=s.n');
        self::assertInstanceOf(UpdateJoinedStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::MySql));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->targets, $copy->targets);
        self::assertSame($statement->from, $copy->from);
        self::assertSame($statement->writes, $copy->writes);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithWhereAddsAPredicateImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)', 'CREATE TABLE s(id INT, n INT)')))->bind('UPDATE t JOIN s ON t.id=s.id SET t.n=s.n');
        self::assertInstanceOf(UpdateJoinedStatement::class, $statement);
        $changed = $statement->withWhere(Expression::binary('>', Expression::reference(['t', 'id'], Dialect::MySql), Expression::literal(1, Dialect::MySql)));
        self::assertSame('UPDATE `t` INNER JOIN `s` ON (`t`.`id` = `s`.`id`) SET `t`.`n` = `s`.`n` WHERE (`t`.`id` > 1)', $changed->toString());
        self::assertNull($statement->where);
        self::assertNull($changed->withWhere(null)->where);
    }

    public function testWithAssignmentsReplacesTheWritesImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)', 'CREATE TABLE s(id INT, n INT)'));
        $statement = $binder->bind('UPDATE t JOIN s ON t.id=s.id SET t.n=s.n');
        $other = $binder->bind('UPDATE t JOIN s ON t.id=s.id SET t.id=s.id');
        self::assertInstanceOf(UpdateJoinedStatement::class, $statement);
        self::assertInstanceOf(UpdateJoinedStatement::class, $other);
        $changed = $statement->withAssignments($other->writes);
        self::assertSame('UPDATE `t` INNER JOIN `s` ON (`t`.`id` = `s`.`id`) SET `t`.`id` = `s`.`id`', $changed->toString());
        self::assertSame('n', $statement->writes[0]->destinations()[0]->column()->columnBinding()?->column->name);
    }

    public function testRequiresTheMySqlDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)', 'CREATE TABLE s(id INT, n INT)')))->bind('UPDATE t JOIN s ON t.id=s.id SET t.n=s.n');
        self::assertInstanceOf(UpdateJoinedStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new UpdateJoinedStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), $statement->from, $statement->targets, $statement->writes);
    }
}
