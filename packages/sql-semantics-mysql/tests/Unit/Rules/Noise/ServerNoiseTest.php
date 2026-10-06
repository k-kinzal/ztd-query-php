<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Noise\ServerNoise;

#[CoversClass(ServerNoise::class)]
#[Small]
final class ServerNoiseTest extends TestCase
{
    public function testPositionsListsTheOptionalWordsAndCommas(): void
    {
        self::assertSame([0], ServerNoise::positions()['opt_work: WORK_SYM']);
        self::assertSame([1], ServerNoise::positions()['drop_ts_options: drop_ts_options_list , drop_ts_option']);
    }

    public function testSynonymsMapBeginToStart(): void
    {
        self::assertSame([0 => 'START_SYM'], ServerNoise::synonyms()['begin_or_start: BEGIN_SYM']);
    }
}
