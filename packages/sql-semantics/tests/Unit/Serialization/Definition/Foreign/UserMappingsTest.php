<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\PostgreSql\CreateUserMappingStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Serialization\Definition\Foreign\UserMappings::class)]
#[Medium]
final class UserMappingsTest extends TestCase
{
    public function testWriteReturnsNullForAnotherOperation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertNull(\SqlSemantics\Serialization\Definition\Foreign\UserMappings::write($statement));
    }

    #[TestWith(['CREATE USER MAPPING FOR USER SERVER remote'])]
    #[TestWith(['CREATE USER MAPPING IF NOT EXISTS FOR PUBLIC SERVER remote'])]
    #[TestWith(['DROP USER MAPPING IF EXISTS FOR SESSION_USER SERVER remote'])]
    #[TestWith(['DROP USER MAPPING FOR "CURRENT_USER" SERVER remote'])]
    #[TestWith(["ALTER USER MAPPING FOR USER SERVER remote OPTIONS (SET user 'value', DROP password)"])]
    public function testWritePreservesTheStatementFormAndOperands(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $first = $binder->bind($sql);
        $second = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($first));
        self::assertSame($first::class, $second::class);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($first), (new \SqlSemantics\SimpleSerializer())->serialize($second));
    }

    public function testWriteKeepsConnectionOptionTextWithinLiteralBoundaries(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER MAPPING FOR USER SERVER remote');
        self::assertInstanceOf(CreateUserMappingStatement::class, $statement);
        $value = Expression::literal("user'); DROP SCHEMA app; --", Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $changed = $statement->withOptions([new ForeignOption('user', $value)]);
        self::assertSame($value->text, $changed->options[0]->value->text);
        self::assertSame('user', $changed->options[0]->name);
    }

}
