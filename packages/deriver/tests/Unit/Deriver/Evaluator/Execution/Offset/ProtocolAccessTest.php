<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Offset;

use Deriver\Deriver\Evaluator\Execution\Offset\ProtocolAccess;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Execution\Offset\ProtocolAccess
 */
#[CoversClass(ProtocolAccess::class)]
#[UsesClass(Term::class)]
#[Small]
final class ProtocolAccessTest extends TestCase
{
    public function testRetainsKeysWithoutScalarConversion(): void
    {
        $key = Term::array([]);
        $access = new ProtocolAccess(new Term(\Deriver\Deriver\Evaluator\TermKind::Object, 'box'), $key, ['first','second']);
        self::assertSame($key, $access->key);
        self::assertSame(['first','second'], $access->remaining);
        self::assertSame('box', $access->receiver->literal);
    }
}
