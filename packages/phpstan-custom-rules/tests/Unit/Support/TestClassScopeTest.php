<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class TestClassScopeTest extends TestCase
{
    public function testPlaceholder(): void
    {
        $this->expectNotToPerformAssertions();
    }
}
