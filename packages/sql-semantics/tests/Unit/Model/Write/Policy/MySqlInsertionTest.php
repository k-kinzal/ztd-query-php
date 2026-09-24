<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Write\Policy\MySqlInsertion;
use SqlSemantics\Model\Write\Policy\Scheduling;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MySqlInsertion::class)]
#[Medium]
final class MySqlInsertionTest extends TestCase
{
    public function testDialectIsMySql(): void
    {
        self::assertSame(Dialect::MySql, (new MySqlInsertion())->dialect());
    }

    public function testDefaultsToOrdinarySchedulingWithoutIgnore(): void
    {
        $policy = new MySqlInsertion();
        self::assertSame(Scheduling::Default, $policy->scheduling);
        self::assertFalse($policy->ignore);
    }

    public function testBindsSchedulingAndIgnoreFromTheStatement(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('INSERT LOW_PRIORITY IGNORE INTO t VALUES(1)');
        self::assertInstanceOf(InsertStatement::class, $statement);
        self::assertInstanceOf(MySqlInsertion::class, $statement->policy);
        self::assertSame(Scheduling::LowPriority, $statement->policy->scheduling);
        self::assertTrue($statement->policy->ignore);
        self::assertSame('INSERT LOW_PRIORITY IGNORE INTO `t` VALUES (1)', $statement->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(InsertStatement::class, $rebound);
        self::assertInstanceOf(MySqlInsertion::class, $rebound->policy);
        self::assertSame(Scheduling::LowPriority, $rebound->policy->scheduling);
        self::assertTrue($rebound->policy->ignore);
    }
}
