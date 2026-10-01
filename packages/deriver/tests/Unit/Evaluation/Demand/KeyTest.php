<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Demand;

use Deriver\Evaluation\Demand\Key;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Demand\Key
 */
#[CoversClass(Key::class)]
#[Small]
final class KeyTest extends TestCase
{
    public function testIdSeparatesStateProjectionContextAndGeneration(): void
    {
        $key = new Key('snapshot', 'f', 'entry', 'value', 'return', 'input', 'state', 'guard');
        $epoch = new Key('snapshot', 'f', 'entry', 'value', 'return', 'input', 'state', 'guard', 1);
        $projection = new Key('snapshot', 'f', 'entry', 'value', 'field', 'input', 'state', 'guard');
        self::assertSame($key->id(), (clone $key)->id());
        self::assertNotSame($key->id(), $epoch->id());
        self::assertNotSame($key->id(), $projection->id());
    }
}
