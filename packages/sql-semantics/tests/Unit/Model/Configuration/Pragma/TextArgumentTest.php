<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Pragma;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Pragma\TextArgument;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Configuration\AssignPragmaStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TextArgument::class)]
#[Medium]
final class TextArgumentTest extends TestCase
{
    public function testRetainsTheQuotedTextLiteral(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind("PRAGMA journal_mode='wal'");
        self::assertInstanceOf(AssignPragmaStatement::class, $statement);
        $argument = $statement->value;
        self::assertInstanceOf(TextArgument::class, $argument);
        self::assertSame(LiteralKind::Text, $argument->literal->literalKind);
        self::assertSame("'wal'", $argument->literal->text);
        self::assertSame("PRAGMA \"journal_mode\" = 'wal'", $statement->toString());
    }

    public function testRejectsANumericLiteral(): void
    {
        $literal = Expression::literal(1, Dialect::Sqlite);
        self::assertInstanceOf(Literal::class, $literal);
        $this->expectException(InvalidStructure::class);
        new TextArgument($literal);
    }
}
