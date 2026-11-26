<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Dml\ValueRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ValueRule::class)]
#[Medium]
final class ValueRuleTest extends TestCase
{
    public function testRowLowersTheValues(): void
    {
        self::assertSame('INSERT INTO t VALUES (1, 2)', (new Inspection())->inspect('insert t values (1, 2)')->toString());
    }

    public function testValueLowersDefault(): void
    {
        self::assertSame('UPDATE t SET a = DEFAULT', (new Inspection('mysql-5.6.51'))->inspect('update t set a = default')->toString());
    }

    public function testAssignmentsLowersEveryAssignmentList(): void
    {
        self::assertSame('INSERT INTO t SET a = 1, b = 2 ON DUPLICATE KEY UPDATE a = 3', (new Inspection('mysql-5.6.51'))->inspect('insert t set a = 1, b := 2 on duplicate key update a = 3')->toString());
    }

    public function testAssignmentLowersColumnAndValue(): void
    {
        self::assertSame('UPDATE t SET db.t.a = a + 1', (new Inspection())->inspect('update t set db.t.a = a + 1')->toString());
    }
}
