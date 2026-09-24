<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Procedural\Descriptions;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Inspection\Schema\DescribeTableStatement;
use SqlSemantics\Model\Statement\Plan\ExplainStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Descriptions::class)]
#[Medium]
final class DescriptionsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindSeparatesTableDescriptionsFromExplainedStatements(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind("EXPLAIN t 'i%'");
        self::assertInstanceOf(DescribeTableStatement::class, $statement);
        self::assertSame('i%', $statement->pattern);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
        self::assertInstanceOf(ExplainStatement::class, $binder->bind('EXPLAIN SELECT 1'));
    }

    public function testBindKeepsAnUnknownTableAsADiagnosedReference(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DESC app.missing', strict: false);
        self::assertInstanceOf(DescribeTableStatement::class, $statement);
        self::assertFalse($statement->table->declaration->resolved);
        self::assertNull($statement->pattern);
    }

    #[TestWith(["X'4142'", 'AB'])]
    #[TestWith(['0x142', "\x01B"])]
    #[TestWith(["b'01000001'", 'A'])]
    #[TestWith(['0b1', "\x01"])]
    #[TestWith(["'a''b'", "a'b"])]
    #[TestWith(['`c`', 'c'])]
    public function testPatternDecodesEachSpelling(string $text, string $pattern): void
    {
        $name = str_starts_with($text, "'") ? 'TEXT_STRING' : 'IDENT';
        self::assertSame($pattern, Descriptions::pattern(new Token(0, $name, $text, 0), new Identifiers(Dialect::MySql)));
    }

    public function testBitsPadsTheMostSignificantByte(): void
    {
        self::assertSame("\x01\x00", Descriptions::bits('100000000'));
    }
}
