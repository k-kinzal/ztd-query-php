<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Catalog\Finding;
use SqlCatalog\Core\Catalog\FindingRule;
use SqlCatalog\Core\Catalog\Severity;

#[CoversClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(Severity::class)]
final class FindingTest extends TestCase
{
    public function testOfTakesTheSeverityOfTheRule(): void
    {
        $finding = Finding::of(FindingRule::ExternalInput, 'a message');
        self::assertSame(Severity::High, $finding->severity);
        self::assertSame('a message', $finding->message);
    }

    public function testTheSeverityCanBeStatedOutright(): void
    {
        $finding = new Finding(FindingRule::DynamicSql, Severity::Low, 'a message');
        self::assertSame(Severity::Low, $finding->severity);
    }
}
