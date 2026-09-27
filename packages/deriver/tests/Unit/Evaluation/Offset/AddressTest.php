<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Offset;

use Deriver\Evaluation\Offset\Address;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Offset\Address
 */
#[CoversClass(Address::class)]
#[UsesClass(Term::class)]
#[Small]
final class AddressTest extends TestCase
{
    public function testRetainsRawKeyAndAppendSyntax(): void
    {
        $key = Term::constant('01');
        $address = new Address('parent', $key);
        self::assertSame($key, $address->key);
        self::assertSame('parent', $address->parent);
        self::assertNull((new Address('parent', null))->key);
    }
}
