<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Type\Affinity;

#[CoversClass(Affinity::class)]
#[Small]
final class AffinityTest extends TestCase
{
    public function testListsTheFiveStorageAffinities(): void
    {
        self::assertSame(['integer', 'text', 'blob', 'real', 'numeric'], array_column(Affinity::cases(), 'value'));
    }
}
