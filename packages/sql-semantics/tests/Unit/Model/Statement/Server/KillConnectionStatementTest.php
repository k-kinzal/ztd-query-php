<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Server\KillConnectionStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(KillConnectionStatement::class)]
#[Medium]
final class KillConnectionStatementTest extends TestCase
{
    public function testWithOriginRetainsSemanticOperands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('KILL CONNECTION 42');
        self::assertInstanceOf(KillConnectionStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $statement->connectionId);
        self::assertSame('42', $statement->connectionId->text);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('KILL CONNECTION 42');
        self::assertInstanceOf(KillConnectionStatement::class, $statement);
        $origin = new \SqlSemantics\Model\Statement\Origin('s0', $statement->source, Dialect::Sqlite);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new KillConnectionStatement($origin, $statement->connectionId);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7'])]
    public function testPreservesASessionRequestWhenRemovingRedundantParentheses(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('KILL (((SYSTEM_USER())))');
        self::assertInstanceOf(KillConnectionStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\ContextReference::class, $statement->connectionId);
        self::assertSame('KILL CONNECTION SYSTEM_USER()', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $roundTrip = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(KillConnectionStatement::class, $roundTrip);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\ContextReference::class, $roundTrip->connectionId);
        self::assertSame($statement->connectionId->request, $roundTrip->connectionId->request);
    }
}
