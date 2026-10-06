<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Truth;

#[CoversClass(Truth::class)]
#[Small]
final class TruthTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['TRUE', 'FALSE', 'UNKNOWN'], array_column(Truth::cases(), 'value'));
    }
}
