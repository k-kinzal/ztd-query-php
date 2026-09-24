<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Utility\SystemSettings;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\System as Statement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SystemSettings::class)]
#[Medium]
final class SystemSettingsTest extends TestCase
{
    #[TestWith(['ALTER SYSTEM SET a.b TO 1, \'x\'', Statement\AlterSystemSetStatement::class, 'ALTER SYSTEM SET "a"."b" = 1, \'x\''])]
    #[TestWith(['ALTER SYSTEM SET work_mem = - 5', Statement\AlterSystemSetStatement::class, 'ALTER SYSTEM SET "work_mem" = -5'])]
    #[TestWith(['ALTER SYSTEM SET work_mem TO DEFAULT', Statement\AlterSystemSetStatement::class, 'ALTER SYSTEM SET "work_mem" = DEFAULT'])]
    #[TestWith(['ALTER SYSTEM RESET work_mem', Statement\AlterSystemResetStatement::class, 'ALTER SYSTEM RESET "work_mem"'])]
    #[TestWith(['ALTER SYSTEM RESET ALL', Statement\AlterSystemResetAllStatement::class, 'ALTER SYSTEM RESET ALL'])]
    public function testBindSeparatesAssignmentsAndRemovals(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertSame($class, $statement::class);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }
}
