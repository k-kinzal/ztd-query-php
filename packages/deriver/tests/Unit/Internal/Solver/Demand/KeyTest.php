<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Demand;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Demand\Key
 */
#[CoversClass(\Deriver\Internal\Solver\Demand\Key::class)]
#[Small]
final class KeyTest extends TestCase
{
    public function testIdSeparatesStateProjectionContextAndGeneration(): void
    {
        $key = new \Deriver\Internal\Solver\Demand\Key('snapshot', 'f', 'entry', 'value', 'return', 'input', 'state', 'guard');
        $epoch = new \Deriver\Internal\Solver\Demand\Key('snapshot', 'f', 'entry', 'value', 'return', 'input', 'state', 'guard', 1);
        $projection = new \Deriver\Internal\Solver\Demand\Key('snapshot', 'f', 'entry', 'value', 'field', 'input', 'state', 'guard');
        self::assertSame($key->id(), (clone $key)->id());
        self::assertNotSame($key->id(), $epoch->id());
        self::assertNotSame($key->id(), $projection->id());
    }
}
