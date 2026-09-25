<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\PostgreSql\ImportForeignSchemaStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Serialization\Definition\Foreign\ForeignImports::class)]
#[Medium]
final class ForeignImportsTest extends TestCase
{
    public function testWriteReturnsNullForAnotherOperation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertNull(\SqlSemantics\Serialization\Definition\Foreign\ForeignImports::write($statement));
    }

    public function testWriteKeepsOptionTextInsideItsLiteralBoundary(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('IMPORT FOREIGN SCHEMA ext FROM SERVER remote INTO app');
        self::assertInstanceOf(ImportForeignSchemaStatement::class, $statement);
        $value = Expression::literal("value'); DROP SCHEMA app; --", Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $changed = $statement->withOptions([new ForeignOption('x"y', $value)]);
        $rebound = $binder->bind($changed->toString());
        self::assertInstanceOf(ImportForeignSchemaStatement::class, $rebound);
        self::assertSame('x"y', $rebound->options[0]->name);
        self::assertSame($value->text, $rebound->options[0]->value->text);
        self::assertSame($changed->toString(), (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }

}
