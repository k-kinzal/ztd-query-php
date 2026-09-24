<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Role\RoleValidity;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoleValidity::class)]
#[Medium]
final class RoleValidityTest extends TestCase
{
    public function testRetainsTheTimestampTextWithoutParsingIt(): void
    {
        $until = Expression::literal('not a timestamp', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $until);
        $validity = new RoleValidity($until);
        self::assertSame($until, $validity->until);
        self::assertSame(LiteralKind::Text, $validity->until->literalKind);
        self::assertSame("'not a timestamp'", $validity->until->text);
    }

    public function testRejectsANonTextLiteral(): void
    {
        $number = Expression::literal(20240101, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $number);
        self::assertSame(LiteralKind::Number, $number->literalKind);
        $this->expectException(InvalidStructure::class);
        new RoleValidity($number);
    }

    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testRejectsALiteralOfAnotherDialect(Dialect $dialect): void
    {
        $until = Expression::literal('infinity', $dialect);
        self::assertInstanceOf(Literal::class, $until);
        $this->expectException(InvalidStructure::class);
        new RoleValidity($until);
    }

    public function testValidUntilBindsFromAnAlteration(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("ALTER ROLE r VALID UNTIL 'infinity'");
        self::assertInstanceOf(AlterRoleStatement::class, $statement);
        $option = $statement->options[0];
        self::assertInstanceOf(RoleValidity::class, $option);
        self::assertSame("'infinity'", $option->until->text);
        self::assertSame('ALTER ROLE "r" VALID UNTIL \'infinity\'', $statement->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(AlterRoleStatement::class, $rebound);
        self::assertSame($statement->toString(), $rebound->toString());
    }
}
