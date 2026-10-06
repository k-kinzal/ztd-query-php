<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\QueryNoise;

#[CoversClass(QueryNoise::class)]
#[Small]
final class QueryNoiseTest extends TestCase
{
    public function testPositionsListsTheNoiseOfTheFamily(): void
    {
        self::assertSame([0], QueryNoise::positions()['alias_clause: AS ColId']);
    }
}
