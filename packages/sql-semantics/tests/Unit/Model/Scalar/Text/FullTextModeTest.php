<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Text\FullTextMode;

#[CoversClass(FullTextMode::class)]
final class FullTextModeTest extends TestCase
{
    public function testCasesAreSpelledAsTheirSearchModifierClause(): void
    {
        self::assertSame(['IN NATURAL LANGUAGE MODE', 'WITH QUERY EXPANSION', 'IN BOOLEAN MODE'], array_column(FullTextMode::cases(), 'value'));
    }
}
