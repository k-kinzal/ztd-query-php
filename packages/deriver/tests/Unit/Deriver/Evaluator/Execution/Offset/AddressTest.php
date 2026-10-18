<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Offset;

use Deriver\Deriver\Evaluator\Execution\Offset\Address;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Execution\Offset\Address
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
