<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaNumber;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(PragmaNumber::class)]
#[Small]
final class PragmaNumberTest extends TestCase
{
    public function testNativeTextDropsTheGrammarPlusAndRetainsTheMinus(): void
    {
        self::assertSame('1.5', (new PragmaNumber(NumberSign::Plus, '1.5'))->nativeText());
        self::assertSame('-0xAB', (new PragmaNumber(NumberSign::Minus, '0xAB'))->nativeText());
    }

    public function testRenderWritesTheSignedTokenInTheTransientRequest(): void
    {
        self::assertSame('PRAGMA cache_size = -2000', (new Inspection())->inspect('pragma cache_size = -2000')->toString());
    }
}
