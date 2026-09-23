<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\DefinitionBinder::class)]
#[Medium]
final class DefinitionBinderTest extends TestCase
{
    public function testBindPreservesTheObjectFamilyWhenRoutingDeclarations(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\CreateForeignServerStatement::class, $binder->bind('CREATE SERVER remote FOREIGN DATA WRAPPER fdw'));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\DropFunctionsStatement::class, $binder->bind('DROP FUNCTION f()'));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\CreateUserMappingStatement::class, $binder->bind('CREATE USER MAPPING FOR USER SERVER remote'));
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $binder->bind('SELECT 1'));
    }
}
