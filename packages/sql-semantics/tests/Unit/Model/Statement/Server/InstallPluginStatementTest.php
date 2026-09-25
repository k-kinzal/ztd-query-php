<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Server\InstallPluginStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(InstallPluginStatement::class)]
#[Medium]
final class InstallPluginStatementTest extends TestCase
{
    public function testWithOriginRetainsSemanticOperands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("INSTALL PLUGIN audit SONAME 'audit.so'");
        self::assertInstanceOf(InstallPluginStatement::class, $statement);
        self::assertSame('audit', $statement->name);
        self::assertSame(\SqlSemantics\Model\Scalar\Value\LiteralKind::Text, $statement->library->literalKind);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("INSTALL PLUGIN audit SONAME 'audit.so'");
        self::assertInstanceOf(InstallPluginStatement::class, $statement);
        $origin = new \SqlSemantics\Model\Statement\Origin('s0', $statement->source, Dialect::Sqlite);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new InstallPluginStatement($origin, $statement->name, $statement->library);
    }
}
