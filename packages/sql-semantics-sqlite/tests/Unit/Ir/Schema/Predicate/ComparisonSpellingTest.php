<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\ComparisonSpelling;

#[CoversClass(ComparisonSpelling::class)]
#[Small]
final class ComparisonSpellingTest extends TestCase
{
    public function testKeepsEachAlternativeGlyph(): void
    {
        self::assertSame(['=', '==', '<>', '!=', '<', '<=', '>', '>='], array_column(ComparisonSpelling::cases(), 'value'));
    }
}
