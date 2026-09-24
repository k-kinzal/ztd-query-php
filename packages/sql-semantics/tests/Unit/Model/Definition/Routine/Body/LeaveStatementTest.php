<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\LeaveStatement;
use SqlSemantics\Model\Definition\Routine\Body\LoopStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LeaveStatement::class)]
#[Medium]
final class LeaveStatementTest extends TestCase
{
    public function testNamesTheEnclosingLoop(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() l: LOOP LEAVE l; END LOOP');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(LoopStatement::class, $statement->body);
        self::assertInstanceOf(LeaveStatement::class, $statement->body->statements[0]);
        self::assertSame('l', $statement->body->statements[0]->label);
    }

    public function testRequiresALabel(): void
    {
        $this->expectException(InvalidStructure::class);
        new LeaveStatement('');
    }

    public function testDiagnosesAnUnknownLabel(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramLabel->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() l: LOOP LEAVE m; END LOOP', strict: false);
    }
}
