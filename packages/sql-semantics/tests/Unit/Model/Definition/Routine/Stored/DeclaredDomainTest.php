<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Stored\DeclaredDomain;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(DeclaredDomain::class)]
#[Medium]
final class DeclaredDomainTest extends TestCase
{
    public function testRetainsTheReturnTypeAndCollation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f() RETURNS VARCHAR(5) CHARSET utf8mb4 COLLATE utf8mb4_bin RETURN 1');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertSame('varchar', $statement->returns->type->name);
        self::assertSame('utf8mb4_bin', $statement->returns->collation);
    }

    public function testRejectsAnotherDialectType(): void
    {
        $this->expectException(InvalidStructure::class);
        new DeclaredDomain(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'));
    }

    public function testRejectsAnEmptyCollation(): void
    {
        $this->expectException(InvalidStructure::class);
        new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'integer'), '');
    }
}
