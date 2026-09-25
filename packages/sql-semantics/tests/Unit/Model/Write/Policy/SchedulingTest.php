<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Write\Policy\MySqlInsertion;
use SqlSemantics\Model\Write\Policy\Scheduling;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Scheduling::class)]
#[Medium]
final class SchedulingTest extends TestCase
{
    public function testRepresentsEveryMySqlSchedulingModifier(): void
    {
        self::assertSame(['', 'LOW_PRIORITY', 'HIGH_PRIORITY', 'DELAYED'], array_column(Scheduling::cases(), 'value'));
    }

    #[TestWith(['INSERT INTO t VALUES(1)', Scheduling::Default, 'INSERT INTO `t` VALUES (1)'])]
    #[TestWith(['INSERT LOW_PRIORITY INTO t VALUES(1)', Scheduling::LowPriority, 'INSERT LOW_PRIORITY INTO `t` VALUES (1)'])]
    #[TestWith(['INSERT HIGH_PRIORITY INTO t VALUES(1)', Scheduling::HighPriority, 'INSERT HIGH_PRIORITY INTO `t` VALUES (1)'])]
    #[TestWith(['INSERT DELAYED INTO t VALUES(1)', Scheduling::Delayed, 'INSERT DELAYED INTO `t` VALUES (1)'])]
    public function testBindsTheModifierFromTheStatement(string $sql, Scheduling $scheduling, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(InsertStatement::class, $statement);
        self::assertInstanceOf(MySqlInsertion::class, $statement->policy);
        self::assertSame($scheduling, $statement->policy->scheduling);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }
}
