<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege\PostgreSql\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectTargets;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ServerObjectClass::class)]
#[Medium]
final class ServerObjectClassTest extends TestCase
{
    public function testRepresentsEveryUnqualifiedObjectClass(): void
    {
        self::assertSame(['DATABASE', 'FOREIGN DATA WRAPPER', 'FOREIGN SERVER', 'LANGUAGE', 'SCHEMA', 'TABLESPACE'], array_column(ServerObjectClass::cases(), 'value'));
    }

    #[TestWith(['CONNECT', 'DATABASE', ServerObjectClass::Database])]
    #[TestWith(['USAGE', 'FOREIGN DATA WRAPPER', ServerObjectClass::ForeignDataWrapper])]
    #[TestWith(['USAGE', 'FOREIGN SERVER', ServerObjectClass::ForeignServer])]
    #[TestWith(['USAGE', 'LANGUAGE', ServerObjectClass::Language])]
    #[TestWith(['CREATE', 'SCHEMA', ServerObjectClass::Schema])]
    #[TestWith(['CREATE', 'TABLESPACE', ServerObjectClass::Tablespace])]
    public function testBindsTheDeclaredClassAndWritesItBack(string $privilege, string $words, ServerObjectClass $class): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('GRANT ' . $privilege . ' ON ' . $words . ' x TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertInstanceOf(ServerObjectTargets::class, $statement->target);
        self::assertSame($class, $statement->target->class);
        self::assertSame('GRANT ' . $privilege . ' ON ' . $words . ' "x" TO "a"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
