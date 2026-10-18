<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Demand;

use Deriver\Deriver\Evaluator\Execution\Demand\Key;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Execution\Demand\Key
 */
#[CoversClass(Key::class)]
#[Small]
final class KeyTest extends TestCase
{
    public function testIdSeparatesStateProjectionContextAndGeneration(): void
    {
        $key = new Key('snapshot', 'f', 'entry', \Deriver\Deriver\Evaluator\Execution\Demand\DemandKind::Value, 'return', 'input', 'state', 'guard');
        $epoch = new Key('snapshot', 'f', 'entry', \Deriver\Deriver\Evaluator\Execution\Demand\DemandKind::Value, 'return', 'input', 'state', 'guard', 1);
        $projection = new Key('snapshot', 'f', 'entry', \Deriver\Deriver\Evaluator\Execution\Demand\DemandKind::Value, 'field', 'input', 'state', 'guard');
        self::assertSame($key->id(), (clone $key)->id());
        self::assertNotSame($key->id(), $epoch->id());
        self::assertNotSame($key->id(), $projection->id());
    }
}
