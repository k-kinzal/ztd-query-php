<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Offset;

use Deriver\Evaluation\Offset\ProtocolAccess;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Offset\ProtocolAccess
 */
#[CoversClass(ProtocolAccess::class)]
#[UsesClass(Term::class)]
#[Small]
final class ProtocolAccessTest extends TestCase
{
    public function testRetainsKeysWithoutScalarConversion(): void
    {
        $key = Term::array([]);
        $access = new ProtocolAccess(new Term('object', 'box'), $key, ['first','second']);
        self::assertSame($key, $access->key);
        self::assertSame(['first','second'], $access->remaining);
        self::assertSame('box', $access->receiver->literal);
    }
}
