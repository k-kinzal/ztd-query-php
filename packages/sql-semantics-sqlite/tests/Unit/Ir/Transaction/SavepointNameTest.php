<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Transaction\SavepointName;

#[CoversClass(SavepointName::class)]
#[Small]
final class SavepointNameTest extends TestCase
{
    public function testQuotedPreservesEmptyAndLongLabels(): void
    {
        $label = str_repeat('é', 130);

        self::assertSame('""', (new SavepointName(''))->quoted());
        self::assertSame('"' . $label . '"', (new SavepointName($label))->quoted());
    }

}
