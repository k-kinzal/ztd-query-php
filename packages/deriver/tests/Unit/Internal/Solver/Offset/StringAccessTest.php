<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Offset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Offset\StringAccess
 */
#[CoversClass(\Deriver\Internal\Solver\Offset\StringAccess::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class StringAccessTest extends TestCase
{
    public function testRetainsCompleteStringStorage(): void
    {
        $location = new \Deriver\Internal\Memory\Location('root', ['text']);
        $access = new \Deriver\Internal\Solver\Offset\StringAccess($location, \Deriver\Value\Term::constant(-1));
        self::assertSame($location, $access->container);
        self::assertSame(-1, $access->key?->native());
    }
}
