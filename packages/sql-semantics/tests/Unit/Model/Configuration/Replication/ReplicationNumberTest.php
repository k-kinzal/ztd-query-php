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

    #[\PHPUnit\Framework\Attributes\TestWith(["X'0F'", 15.0])]
    #[\PHPUnit\Framework\Attributes\TestWith(["x''", 0.0])]
    #[\PHPUnit\Framework\Attributes\TestWith(['0X1F', 31.0])]
    #[\PHPUnit\Framework\Attributes\TestWith(['12.9e3', 12.0])]
    public function testMagnitudeReadsEveryHexadecimalSpelling(string $sql, float $expected): void
    {
        $literal = (new \SqlSemantics\Binding\LiteralBinder(Dialect::MySql))->bind((new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('SELECT ' . $sql)->tokens()[1]);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $literal);
        self::assertSame($expected, ReplicationNumber::magnitude($literal));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(["X'10'", 16.0])]
    #[\PHPUnit\Framework\Attributes\TestWith(['0X10', 16.0])]
    #[\PHPUnit\Framework\Attributes\TestWith(['2.5e1', 25.0])]
    public function testRealReadsUpperCaseHexadecimalAsItsMagnitude(string $sql, float $expected): void
    {
        $literal = (new \SqlSemantics\Binding\LiteralBinder(Dialect::MySql))->bind((new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('SELECT ' . $sql)->tokens()[1]);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $literal);
        self::assertSame($expected, ReplicationNumber::real($literal));
    }

    public function testCheckNamesTheOperandItRejects(): void
    {
        $literal = (new \SqlSemantics\Binding\LiteralBinder(Dialect::MySql))->bind((new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse("SELECT 'p'")->tokens()[1]);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $literal);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('port requires an unsigned MySQL number literal.');
        ReplicationNumber::check($literal, 'port');
    }
}
