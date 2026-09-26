<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Offset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Offset\ProtocolAccess
 */
#[CoversClass(\Deriver\Internal\Solver\Offset\ProtocolAccess::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ProtocolAccessTest extends TestCase
{
    public function testRetainsKeysWithoutScalarConversion(): void
    {
        $key = \Deriver\Value\Term::array([]);
        $access = new \Deriver\Internal\Solver\Offset\ProtocolAccess(new \Deriver\Value\Term('object', 'box'), $key, ['first','second']);
        self::assertSame($key, $access->key);
        self::assertSame(['first','second'], $access->remaining);
        self::assertSame('box', $access->receiver->literal);
    }
}
