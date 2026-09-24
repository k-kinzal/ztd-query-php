<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\RepeatStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RepeatStatement::class)]
#[Medium]
final class RepeatStatementTest extends TestCase
{
    public function testTestsTheConditionAfterEachPass(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE PROCEDURE p(a INT) REPEAT SET a = a + 1; UNTIL a > 3 END REPEAT');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(RepeatStatement::class, $statement->body);
        self::assertNull($statement->body->label);
        self::assertSame('CREATE PROCEDURE `p`(IN `a` integer) REPEAT SET `a` = (`a` + 1); UNTIL(`a` > 3) END REPEAT', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRequiresStatements(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) REPEAT DO 1; UNTIL a END REPEAT');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(RepeatStatement::class, $statement->body);
        $this->expectException(InvalidStructure::class);
        new RepeatStatement(null, [], $statement->body->until);
    }
}
