<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Stored\ExternalRoutineCode;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ExternalRoutineCode::class)]
#[Medium]
final class ExternalRoutineCodeTest extends TestCase
{
    public function testDecodesAQuotedBody(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-9.1.0'))->build());
        $statement = $binder->bind("CREATE PROCEDURE p() LANGUAGE JAVASCRIPT AS 'it''s'");
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(ExternalRoutineCode::class, $statement->body);
        self::assertSame("it's", $statement->body->code);
        self::assertSame("CREATE PROCEDURE `p`() LANGUAGE `JAVASCRIPT` AS 'it''s'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsTheSqlLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        new ExternalRoutineCode('sql', 'x');
    }
}
