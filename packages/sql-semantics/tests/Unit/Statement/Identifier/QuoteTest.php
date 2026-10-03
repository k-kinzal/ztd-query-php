<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Identifier;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\Quote;

#[CoversClass(Quote::class)]
#[UsesClass(Name::class)]
#[Medium]
final class QuoteTest extends TestCase
{
    #[TestWith([Quote::Double, 'a"b'])]
    #[TestWith([Quote::Backtick, 'a`b'])]
    #[TestWith([Quote::Bracket, 'a b'])]
    #[TestWith([Quote::Single, "a'b"])]
    #[TestWith([Quote::None, 'bare'])]
    public function testTheEngineReadsTheDecodedIdentifier(Quote $quote, string $value): void
    {
        $db = new PDO('sqlite::memory:');
        $result = $db->query('SELECT 1 AS ' . (new Name($value, $quote))->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame([$value => 1], $result->fetch(PDO::FETCH_ASSOC));
    }
}
