<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Affinity;

#[CoversClass(Affinity::class)]
#[Small]
final class AffinityTest extends TestCase
{
    public function testListsTheFiveStorageAffinities(): void
    {
        self::assertSame(['integer', 'text', 'blob', 'real', 'numeric'], array_column(Affinity::cases(), 'value'));
    }
}
