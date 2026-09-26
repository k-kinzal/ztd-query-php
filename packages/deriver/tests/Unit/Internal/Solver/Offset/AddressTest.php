<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Offset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Offset\Address
 */
#[CoversClass(\Deriver\Internal\Solver\Offset\Address::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class AddressTest extends TestCase
{
    public function testRetainsRawKeyAndAppendSyntax(): void
    {
        $key = \Deriver\Value\Term::constant('01');
        $address = new \Deriver\Internal\Solver\Offset\Address('parent', $key);
        self::assertSame($key, $address->key);
        self::assertSame('parent', $address->parent);
        self::assertNull((new \Deriver\Internal\Solver\Offset\Address('parent', null))->key);
    }
}
