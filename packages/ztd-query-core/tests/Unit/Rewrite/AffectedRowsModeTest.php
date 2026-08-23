<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Rewrite\AffectedRowsMode;

#[CoversClass(AffectedRowsMode::class)]
final class AffectedRowsModeTest extends TestCase
{
    public function testDefinesNativeUpdateCountingConventions(): void
    {
        self::assertSame('none', AffectedRowsMode::None->value);
        self::assertSame('changed', AffectedRowsMode::Changed->value);
        self::assertSame('matched', AffectedRowsMode::Matched->value);
    }
}
