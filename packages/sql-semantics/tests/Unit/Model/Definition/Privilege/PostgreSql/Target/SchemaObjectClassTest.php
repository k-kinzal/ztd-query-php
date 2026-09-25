<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege\PostgreSql\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectTargets;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SchemaObjectClass::class)]
#[Medium]
final class SchemaObjectClassTest extends TestCase
{
    public function testRepresentsEverySchemaQualifiedObjectClass(): void
    {
        self::assertSame(['SEQUENCE', 'DOMAIN', 'TYPE'], array_column(SchemaObjectClass::cases(), 'value'));
    }

    #[TestWith(['SEQUENCE', SchemaObjectClass::Sequence])]
    #[TestWith(['DOMAIN', SchemaObjectClass::Domain])]
    #[TestWith(['TYPE', SchemaObjectClass::Type])]
    public function testBindsTheDeclaredClassAndWritesItBack(string $word, SchemaObjectClass $class): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('GRANT USAGE ON ' . $word . ' app.x TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertInstanceOf(SchemaObjectTargets::class, $statement->target);
        self::assertSame($class, $statement->target->class);
        self::assertSame('GRANT USAGE ON ' . $word . ' "app"."x" TO "a"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
