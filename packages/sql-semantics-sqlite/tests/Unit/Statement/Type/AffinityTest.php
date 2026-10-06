<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;

#[CoversClass(Affinity::class)]
#[Small]
final class AffinityTest extends TestCase
{
    public function testCasesNameTheFiveAffinities(): void
    {
        self::assertSame(['Integer', 'Text', 'Blob', 'Real', 'Numeric'], array_column(Affinity::cases(), 'name'));
    }
}
