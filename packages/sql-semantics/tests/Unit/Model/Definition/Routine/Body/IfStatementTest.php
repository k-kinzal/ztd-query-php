<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\IfStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(IfStatement::class)]
#[Medium]
final class IfStatementTest extends TestCase
{
    public function testWritesBranchesInOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE PROCEDURE p(a INT) IF a > 0 THEN DO 1; ELSEIF a < 0 THEN DO 2; ELSE DO 3; END IF');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(IfStatement::class, $statement->body);
        self::assertSame('CREATE PROCEDURE `p`(IN `a` integer) IF(`a` > 0) THEN DO 1; ELSEIF(`a` < 0) THEN DO 2; ELSE DO 3; END IF', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRequiresABranch(): void
    {
        $this->expectException(InvalidStructure::class);
        new IfStatement([]);
    }
}
