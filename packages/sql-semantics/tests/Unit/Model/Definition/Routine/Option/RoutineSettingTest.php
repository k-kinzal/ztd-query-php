<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\CurrentSetting;
use SqlSemantics\Model\Definition\Routine\Option\RoutineSetting;
use SqlSemantics\Model\Statement\Definition\Routine\AlterRoutineStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoutineSetting::class)]
#[Medium]
final class RoutineSettingTest extends TestCase
{
    public function testRetainsACapturedValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f() SET work_mem FROM CURRENT');
        self::assertInstanceOf(AlterRoutineStatement::class, $statement);
        $change = $statement->changes[0];
        self::assertInstanceOf(RoutineSetting::class, $change);
        self::assertInstanceOf(CurrentSetting::class, $change->setting);
        self::assertSame(['work_mem'], $change->setting->name);
    }
}
