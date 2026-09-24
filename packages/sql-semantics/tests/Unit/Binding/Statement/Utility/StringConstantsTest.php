<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Utility\StringConstants;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Loading\LoadLibraryStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(StringConstants::class)]
#[Medium]
final class StringConstantsTest extends TestCase
{
    #[TestWith(["LOAD 'a'", "'a'"])]
    #[TestWith(["LOAD e'a'", "e'a'"])]
    #[TestWith(["LOAD U&'a' UESCAPE '!'", "U&'a' UESCAPE '!'"])]
    #[TestWith(['LOAD $t$a$t$', '$t$a$t$'])]
    public function testLiteralKeepsTheWrittenSpelling(string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(LoadLibraryStatement::class, $statement);
        self::assertSame($expected, $statement->file->text);
        self::assertSame(LiteralKind::Text, $statement->file->literalKind);
    }
}
