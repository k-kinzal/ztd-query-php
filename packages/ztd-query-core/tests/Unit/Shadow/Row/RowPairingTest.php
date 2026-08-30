<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Row;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Row\RowChange;
use ZtdQuery\Shadow\Row\RowPairing;

#[CoversClass(RowPairing::class)]
#[UsesClass(RowChange::class)]
final class RowPairingTest extends TestCase
{
    public function testPairRecordsBothPositionsAndTheChangeWhereTheRowsDiffer(): void
    {
        $pairing = new RowPairing();

        $pairing->pair(0, 2, ['id' => 1], ['id' => 2]);

        self::assertSame(
            [[0], [2], [[['id' => 1], ['id' => 2]]]],
            [
                $pairing->beforePositions(),
                $pairing->afterPositions(),
                array_map(static fn (RowChange $c): array => [$c->before, $c->after], $pairing->changes()),
            ],
        );
    }

    public function testPairRecordsNoChangeWhereTheRowIsWhatItWas(): void
    {
        $pairing = new RowPairing();

        $pairing->pair(0, 0, ['id' => 1], ['id' => 1]);

        self::assertSame([[0], []], [$pairing->beforePositions(), $pairing->changes()]);
    }

    public function testBeforePositionsAnswersNothingWhereNothingWasPaired(): void
    {
        self::assertSame([], (new RowPairing())->beforePositions());
    }

    public function testAfterPositionsAnswersNothingWhereNothingWasPaired(): void
    {
        self::assertSame([], (new RowPairing())->afterPositions());
    }

    public function testChangesAnswersNothingWhereNothingWasPaired(): void
    {
        self::assertSame([], (new RowPairing())->changes());
    }
}
