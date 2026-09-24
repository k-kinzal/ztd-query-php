<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege\PostgreSql\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaScopedClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaScopedTargets;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SchemaScopedTargets::class)]
#[Medium]
final class SchemaScopedTargetsTest extends TestCase
{
    public function testRetainsTheClassAndSchemasInRequestOrder(): void
    {
        $targets = new SchemaScopedTargets(SchemaScopedClass::Tables, ['app', 'public']);
        self::assertSame(SchemaScopedClass::Tables, $targets->class);
        self::assertSame(['app', 'public'], $targets->schemas);
    }

    public function testReadsTheSchemasOfABoundGrant(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA s, app TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertEquals(new SchemaScopedTargets(SchemaScopedClass::Tables, ['s', 'app']), $statement->target);
        self::assertSame('GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA "s", "app" TO "a"', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsAnEmptySchemaName(): void
    {
        $this->expectException(InvalidStructure::class);
        new SchemaScopedTargets(SchemaScopedClass::Sequences, ['app', '']);
    }
}
