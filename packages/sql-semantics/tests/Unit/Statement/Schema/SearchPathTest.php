<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(SearchPath::class)]
#[Small]
final class SearchPathTest extends TestCase
{
    public function testLookupOrderRetainsTheSuppliedNames(): void
    {
        $app = new Name('app');
        $public = new Name('public');
        self::assertSame([$app, $public], (new SearchPath($app, $public))->schemas);
    }
}
