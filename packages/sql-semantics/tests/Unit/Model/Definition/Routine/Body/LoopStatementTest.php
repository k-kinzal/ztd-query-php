<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\LeaveStatement;
use SqlSemantics\Model\Definition\Routine\Body\LoopStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LoopStatement::class)]
#[Medium]
final class LoopStatementTest extends TestCase
{
    public function testRepeatsLabeledStatements(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE PROCEDURE p() spin: LOOP LEAVE spin; END LOOP');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(LoopStatement::class, $statement->body);
        self::assertSame('CREATE PROCEDURE `p`() `spin` : LOOP LEAVE `spin`; END LOOP `spin`', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRequiresStatements(): void
    {
        $this->expectException(InvalidStructure::class);
        new LoopStatement(null, []);
    }

    public function testRejectsAnEmptyLabel(): void
    {
        $this->expectException(InvalidStructure::class);
        new LoopStatement('', [new LeaveStatement('x')]);
    }
}
