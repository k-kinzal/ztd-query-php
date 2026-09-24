<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Text\TrimSide;

#[CoversClass(TrimSide::class)]
final class TrimSideTest extends TestCase
{
    public function testCasesAreSpelledAsTheirSqlKeyword(): void
    {
        self::assertSame(['BOTH', 'LEADING', 'TRAILING'], array_column(TrimSide::cases(), 'value'));
    }
}
