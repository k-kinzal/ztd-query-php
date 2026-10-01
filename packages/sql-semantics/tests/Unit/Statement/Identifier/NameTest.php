<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Identifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\Quote;

#[CoversClass(Name::class)]
#[Small]
final class NameTest extends TestCase
{
    #[TestWith(['foo', Quote::None, 'foo'])]
    #[TestWith(['a"b', Quote::Double, '"a""b"'])]
    #[TestWith(['a`b', Quote::Backtick, '`a``b`'])]
    #[TestWith(['a b', Quote::Bracket, '[a b]'])]
    #[TestWith(["a'b", Quote::Single, "'a''b'"])]
    #[TestWith(['', Quote::Double, '""'])]
    public function testToStringEscapesTheDecodedIdentifier(string $value, Quote $quote, string $sql): void
    {
        $name = new Name($value, $quote);
        self::assertSame($value, $name->value);
        self::assertSame($sql, $name->toString());
    }
}
