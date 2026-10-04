<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowListing;

#[CoversClass(ShowListing::class)]
#[Small]
final class ShowListingTest extends TestCase
{
    public function testFullTellsWhetherColumnsAreAdded(): void
    {
        self::assertSame([true, false, true], array_map(static fn (ShowListing $listing): bool => $listing->full(), ShowListing::cases()));
    }
}
