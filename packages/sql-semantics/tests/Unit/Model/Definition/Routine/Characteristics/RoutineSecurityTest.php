<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Characteristics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Statement\Definition\MySql\AlterProcedureStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoutineSecurity::class)]
#[Medium]
final class RoutineSecurityTest extends TestCase
{
    #[TestWith(['DEFINER', RoutineSecurity::Definer])]
    #[TestWith(['INVOKER', RoutineSecurity::Invoker])]
    public function testSecurityRecordsWhosePrivilegesApply(string $sql, RoutineSecurity $security): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER PROCEDURE p SQL SECURITY ' . $sql);
        self::assertInstanceOf(AlterProcedureStatement::class, $statement);
        self::assertSame($security, $statement->changes->security);
        self::assertStringEndsWith('SQL SECURITY ' . $sql, $statement->toString());
    }
}
