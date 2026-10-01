<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\SearchPath;

#[CoversClass(SearchPath::class)]
#[Small]
final class SearchPathTest extends TestCase
{
    public function testKeepsTheSchemasInSearchOrder(): void
    {
        self::assertSame(['app', 'public'], (new SearchPath('app', 'public'))->schemas);
        self::assertSame(['app'], (new SearchPath('app'))->schemas);
    }
}
