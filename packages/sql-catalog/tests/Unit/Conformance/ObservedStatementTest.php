<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Conformance\ObservedStatement;

#[CoversClass(ObservedStatement::class)]
final class ObservedStatementTest extends TestCase
{
    public function testNormalizedCollapsesWhitespace(): void
    {
        self::assertSame('SELECT 1 FROM t', (new ObservedStatement("  SELECT\n  1\tFROM t "))->normalized());
    }

    public function testKeepsWhatTheDriverWasGiven(): void
    {
        $observed = new ObservedStatement('SELECT ?', [1], ['id' => 2], 'a.php');
        self::assertSame([1], $observed->positional);
        self::assertSame(['id' => 2], $observed->named);
        self::assertSame('a.php', $observed->source);
    }
}
