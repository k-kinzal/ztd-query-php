<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege\PostgreSql\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaScopedClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaScopedTargets;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SchemaScopedClass::class)]
#[Medium]
final class SchemaScopedClassTest extends TestCase
{
    public function testRepresentsEverySchemaScopedObjectClass(): void
    {
        self::assertSame(['TABLES', 'SEQUENCES', 'FUNCTIONS', 'PROCEDURES', 'ROUTINES'], array_column(SchemaScopedClass::cases(), 'value'));
    }

    #[TestWith(['SELECT', 'TABLES', SchemaScopedClass::Tables])]
    #[TestWith(['USAGE', 'SEQUENCES', SchemaScopedClass::Sequences])]
    #[TestWith(['EXECUTE', 'FUNCTIONS', SchemaScopedClass::Functions])]
    #[TestWith(['EXECUTE', 'PROCEDURES', SchemaScopedClass::Procedures])]
    #[TestWith(['EXECUTE', 'ROUTINES', SchemaScopedClass::Routines])]
    public function testBindsTheDeclaredClassAndWritesItBack(string $privilege, string $word, SchemaScopedClass $class): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('GRANT ' . $privilege . ' ON ALL ' . $word . ' IN SCHEMA s TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertInstanceOf(SchemaScopedTargets::class, $statement->target);
        self::assertSame($class, $statement->target->class);
        self::assertSame('GRANT ' . $privilege . ' ON ALL ' . $word . ' IN SCHEMA "s" TO "a"', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
