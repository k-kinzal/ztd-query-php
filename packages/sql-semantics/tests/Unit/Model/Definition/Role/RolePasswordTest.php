<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Role\RolePassword;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RolePassword::class)]
#[Medium]
final class RolePasswordTest extends TestCase
{
    public function testRetainsAPostgreSqlTextLiteralAsWritten(): void
    {
        $secret = Expression::literal("se'cret", Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $secret);
        $password = new RolePassword($secret);
        self::assertSame($secret, $password->secret);
        self::assertSame(LiteralKind::Text, $password->secret->literalKind);
        self::assertSame("'se''cret'", $password->secret->text);
    }

    public function testRejectsANonTextLiteral(): void
    {
        $number = Expression::literal(7, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $number);
        self::assertSame(LiteralKind::Number, $number->literalKind);
        $this->expectException(InvalidStructure::class);
        new RolePassword($number);
    }

    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testRejectsALiteralOfAnotherDialect(Dialect $dialect): void
    {
        $secret = Expression::literal('secret', $dialect);
        self::assertInstanceOf(Literal::class, $secret);
        $this->expectException(InvalidStructure::class);
        new RolePassword($secret);
    }

    #[TestWith(['PASSWORD'])]
    #[TestWith(['ENCRYPTED PASSWORD'])]
    public function testPasswordAndEncryptedPasswordBindAsTheSameRequest(string $words): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE ROLE r ' . $words . " 'secret'");
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        $option = $statement->options[0];
        self::assertInstanceOf(RolePassword::class, $option);
        self::assertSame("'secret'", $option->secret->text);
        self::assertSame('CREATE ROLE "r" PASSWORD \'secret\'', $statement->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(CreateRoleStatement::class, $rebound);
        self::assertSame($statement->toString(), $rebound->toString());
    }
}
