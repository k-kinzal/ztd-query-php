<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\SimpleCaseStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SimpleCaseStatement::class)]
#[Medium]
final class SimpleCaseStatementTest extends TestCase
{
    public function testWritesBranchesInOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE PROCEDURE p(a INT) CASE a WHEN 1 THEN DO 1; WHEN 2 THEN DO 2; END CASE');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(SimpleCaseStatement::class, $statement->body);
        self::assertSame('CREATE PROCEDURE `p`(IN `a` integer) CASE `a` WHEN 1 THEN DO 1; WHEN 2 THEN DO 2; END CASE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRequiresABranch(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) CASE a WHEN 1 THEN DO 1; END CASE');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(SimpleCaseStatement::class, $statement->body);
        $this->expectException(InvalidStructure::class);
        new SimpleCaseStatement($statement->body->operand, []);
    }
}
