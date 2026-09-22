<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\CallSite;

#[CoversClass(CallSite::class)]
final class CallSiteTest extends TestCase
{
    public function testDisplayWritesTheFileAndTheLine(): void
    {
        self::assertSame('src/a.php:12', (new CallSite('src/a.php', 12, 'App\\R::find', 'pdo.query'))->display());
    }

    public function testKeepsTheEnclosingFunctionAndTheCall(): void
    {
        $site = new CallSite('src/a.php', 12, 'App\\R::find', 'pdo.query');
        self::assertSame('App\\R::find', $site->function);
        self::assertSame('pdo.query', $site->sink);
    }
}
