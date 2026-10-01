<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\LiteralExpressionReader;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\SqliteBlob;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(LiteralExpressionReader::class)]
#[Medium]
final class LiteralExpressionReaderTest extends TestCase
{
    #[TestWith(['1.0', Builtin::DoublePrecision])]
    #[TestWith(['1_000.3_0E+0_2', Builtin::DoublePrecision])]
    #[TestWith(['1_000', Builtin::Integer])]
    #[TestWith(['0XFF', Builtin::Integer])]
    #[TestWith(["'a''b'", Builtin::Text])]
    #[TestWith(["x'00Ff'", Builtin::Blob])]
    public function testReadSeparatesLiteralDomainsAndKeepsTheSQLValue(string $sql, Builtin $builtin): void
    {
        $token = (new SqliteParser())->parse('SELECT ' . $sql)->find('term')[0]->tokens()[0];
        $expression = (new LiteralExpressionReader())->read($token);
        self::assertTrue((new SemanticGraph())->containsOnlyValues($expression));
        self::assertSame($sql, $expression->toString());
        $type = $expression->type();
        self::assertInstanceOf(TypeDescriptor::class, $type);
        self::assertSame($builtin, $type->name);
    }

    public function testReadDecodesBinaryBytesInsteadOfKeepingAnOpaqueToken(): void
    {
        $token = (new SqliteParser())->parse("SELECT x'00Ff'")->find('term')[0]->tokens()[0];
        $expression = (new LiteralExpressionReader())->read($token);
        self::assertInstanceOf(SqliteBlob::class, $expression);
        self::assertSame("\0\xff", $expression->value->value);
    }
}
