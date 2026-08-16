<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Sql\SqlTokenKind;

#[CoversClass(SqlTokenKind::class)]
final class SqlTokenKindTest extends TestCase
{
    public function testKindsHaveStableValues(): void
    {
        self::assertSame('word', SqlTokenKind::Word->value);
        self::assertSame('quoted_identifier', SqlTokenKind::QuotedIdentifier->value);
        self::assertSame('string', SqlTokenKind::String->value);
        self::assertSame('number', SqlTokenKind::Number->value);
        self::assertSame('parameter', SqlTokenKind::Parameter->value);
        self::assertSame('symbol', SqlTokenKind::Symbol->value);
        self::assertSame('comment', SqlTokenKind::Comment->value);
        self::assertSame('whitespace', SqlTokenKind::Whitespace->value);
    }
}
