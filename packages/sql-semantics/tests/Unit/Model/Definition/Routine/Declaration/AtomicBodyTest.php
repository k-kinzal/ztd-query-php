<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Declaration\AtomicBody;
use SqlSemantics\Model\Definition\Routine\Declaration\ReturnBody;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AtomicBody::class)]
#[Medium]
final class AtomicBodyTest extends TestCase
{
    public function testRetainsStatementsAndReturnSteps(): void
    {
        $select = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $body = new AtomicBody([$select, new ReturnBody(Expression::literal(1, Dialect::PostgreSql))]);
        self::assertSame($select, $body->statements[0]);
    }

    public function testRejectsAStatementOfAnotherDatabaseLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        new AtomicBody([(new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')]);
    }
}
