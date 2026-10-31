<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\DeallocationWord;

#[CoversClass(DeallocationWord::class)]
#[Small]
final class DeallocationWordTest extends TestCase
{
    public function testCasesRetainTheWrittenAlternatives(): void
    {
        self::assertSame(['DEALLOCATE', 'DROP'], array_column(DeallocationWord::cases(), 'value'));
    }
}
