<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Routine\RoutineCommands;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql\AlterProcedureStatement;
use SqlSemantics\Model\Statement\Definition\MySql\DropProcedureStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoutineCommands::class)]
#[Medium]
final class RoutineCommandsTest extends TestCase
{
    /**
     * @param class-string $class
     */
    #[TestWith(['ALTER PROCEDURE p NO SQL', AlterProcedureStatement::class])]
    #[TestWith(['DROP PROCEDURE p', DropProcedureStatement::class])]
    #[TestWith(['CREATE PROCEDURE p() BEGIN END', CreateProcedureStatement::class])]
    public function testBindRoutesEachRoutineCommand(string $sql, string $class): void
    {
        self::assertInstanceOf($class, (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql));
    }
}
