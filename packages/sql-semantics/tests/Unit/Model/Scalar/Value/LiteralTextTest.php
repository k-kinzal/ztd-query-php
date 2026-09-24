<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\LiteralText;

#[CoversClass(LiteralText::class)]
#[Medium]
final class LiteralTextTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, "'a'", true])]
    #[TestWith([Dialect::PostgreSql, "'a'\n'b'", true])]
    #[TestWith([Dialect::PostgreSql, "E'a\\n'", true])]
    #[TestWith([Dialect::PostgreSql, "U&'a'", true])]
    #[TestWith([Dialect::PostgreSql, "'a' 'b'", false])]
    #[TestWith([Dialect::PostgreSql, '1', false])]
    #[TestWith([Dialect::PostgreSql, "'a' || 'b'", false])]
    #[TestWith([Dialect::PostgreSql, "'unterminated", false])]
    #[TestWith([Dialect::MySql, "N'a'", true])]
    #[TestWith([Dialect::MySql, '"a"', true])]
    #[TestWith([Dialect::MySql, "'a' 'b'", false])]
    #[TestWith([Dialect::Sqlite, "'a'", true])]
    #[TestWith([Dialect::Sqlite, '"a"', false])]
    #[TestWith([Dialect::Sqlite, "'a' -- c", false])]
    #[TestWith([Dialect::Sqlite, '', false])]
    public function testAcceptsExactlyOneTextLiteralToken(Dialect $dialect, string $text, bool $accepted): void
    {
        self::assertSame($accepted, LiteralText::accepts($text, $dialect));
    }
}
