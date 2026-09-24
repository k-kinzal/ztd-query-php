<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\AssignedUserVariable;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class SetStatementTest extends TestCase
{
    public function testWithVariableValuePreservesAssignmentOrderAndTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET @a=1, @b=2');
        self::assertInstanceOf(SetStatement::class, $statement);
        $assignment = $statement->settings[0];
        self::assertInstanceOf(AssignedUserVariable::class, $assignment);
        $changed = $statement->withVariableValue($assignment, Expression::literal(3, Dialect::MySql));
        self::assertSame('SET @`a` = 1, @`b` = 2', $statement->toString());
        self::assertSame('SET @`a` = 3, @`b` = 2', $changed->toString());
        self::assertSame([['a'], ['b']], array_column($changed->settings, 'name'));
    }

    public function testWithVariableValueRejectsAnAssignmentFromAnotherStatement(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SET @a=1');
        self::assertInstanceOf(SetStatement::class, $statement);
        $other = $binder->bind('SET @a=1');
        self::assertInstanceOf(SetStatement::class, $other);


        self::assertInstanceOf(AssignedUserVariable::class, $other->settings[0]);
        $this->expectException(InvalidStructure::class);
        $statement->withVariableValue($other->settings[0], Expression::literal(3, Dialect::MySql));
    }

    public function testWithOriginKeepsTheSettings(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET @a=1, @b=2');
        self::assertInstanceOf(SetStatement::class, $statement);
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('other', $statement->source, Dialect::MySql, [], $statement->origin->context));
        self::assertSame('other', $changed->scopeId);
        self::assertSame($statement->settings, $changed->settings);
        self::assertSame('SET @`a` = 1, @`b` = 2', $changed->toString());
    }

    public function testAssignmentsExcludeCurrentValueSettings(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $current = $binder->bind('SET work_mem FROM CURRENT');
        self::assertInstanceOf(SetStatement::class, $current);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\CurrentSetting::class, $current->settings[0]);
        self::assertSame([], $current->assignments());
        $assigned = $binder->bind("SET LOCAL work_mem='64MB'");
        self::assertInstanceOf(SetStatement::class, $assigned);
        self::assertSame($assigned->settings, $assigned->assignments());
    }

    public function testAssignmentsKeepDefaultUserAndNamedSettingsInOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET @a=1, sql_mode=DEFAULT, SESSION autocommit=1');
        self::assertInstanceOf(SetStatement::class, $statement);
        self::assertSame([AssignedUserVariable::class, \SqlSemantics\Model\Configuration\DefaultSetting::class, \SqlSemantics\Model\Configuration\AssignedSetting::class], array_map(static fn (object $setting): string => $setting::class, $statement->assignments()));
    }

    public function testWithValuesReplacesOneSettingAndKeepsTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SET LOCAL work_mem='64MB'");
        self::assertInstanceOf(SetStatement::class, $statement);
        $setting = $statement->settings[0];
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $setting);
        $changed = $statement->withValues($setting, [Expression::literal('128MB', Dialect::PostgreSql)]);
        self::assertSame('SET LOCAL "work_mem" = \'128MB\'', $changed->toString());
        self::assertSame('SET LOCAL "work_mem" = \'64MB\'', $statement->toString());
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $changed->settings[0]);
        self::assertSame(['work_mem'], $changed->settings[0]->name);
    }

    public function testRejectsConnectionCharacterSetsOutsideMySql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SET LOCAL work_mem='64MB'");
        $this->expectException(InvalidStructure::class);
        new SetStatement($statement->origin, [new \SqlSemantics\Model\Configuration\Connection\ConnectionNames()]);
    }
}
