<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\ConditionalBranch;
use SqlSemantics\Model\Definition\Routine\Body\IfStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ConditionalBranch::class)]
#[Medium]
final class ConditionalBranchTest extends TestCase
{
    public function testRequiresStatements(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) IF a THEN DO 1; DO 2; END IF');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(IfStatement::class, $statement->body);
        self::assertCount(2, $statement->body->branches[0]->statements);
        $this->expectException(InvalidStructure::class);
        new ConditionalBranch($statement->body->branches[0]->condition, []);
    }
}
