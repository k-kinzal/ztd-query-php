<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\ReplicationNumber;
use SqlSemantics\Model\Statement\Server\Administration\CloneRemoteStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReplicationNumber::class)]
#[Medium]
final class ReplicationNumberTest extends TestCase
{
    public function testCheckAcceptsHexadecimalAndRejectsText(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CLONE INSTANCE FROM u@h:0x0f IDENTIFIED BY 'p'");
        self::assertInstanceOf(CloneRemoteStatement::class, $statement);
        ReplicationNumber::check($statement->port, 'port');
        $this->expectException(InvalidStructure::class);
        ReplicationNumber::check($statement->password, 'port');
    }

    public function testCheckRejectsAFractionWhereAnIntegerIsRequired(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CLONE INSTANCE FROM u@h:1.5 IDENTIFIED BY 'p'");
        self::assertInstanceOf(CloneRemoteStatement::class, $statement);
        ReplicationNumber::check($statement->port, 'port');
        $this->expectException(InvalidStructure::class);
        ReplicationNumber::check($statement->port, 'port', true);
    }

    public function testMagnitudeReadsTheIntegerPrefix(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $decimal = $binder->bind("CLONE INSTANCE FROM u@h:12.9 IDENTIFIED BY 'p'");
        $hex = $binder->bind("CLONE INSTANCE FROM u@h:X'0f' IDENTIFIED BY 'p'");
        self::assertInstanceOf(CloneRemoteStatement::class, $decimal);
        self::assertInstanceOf(CloneRemoteStatement::class, $hex);
        self::assertSame(12.0, ReplicationNumber::magnitude($decimal->port));
        self::assertSame(15.0, ReplicationNumber::magnitude($hex->port));
    }

    public function testRealReadsTheFractionalValue(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $decimal = $binder->bind("CLONE INSTANCE FROM u@h:1.5 IDENTIFIED BY 'p'");
        $hex = $binder->bind("CLONE INSTANCE FROM u@h:0x10 IDENTIFIED BY 'p'");
        self::assertInstanceOf(CloneRemoteStatement::class, $decimal);
        self::assertInstanceOf(CloneRemoteStatement::class, $hex);
        self::assertSame(1.5, ReplicationNumber::real($decimal->port));
        self::assertSame(16.0, ReplicationNumber::real($hex->port));
    }
}
