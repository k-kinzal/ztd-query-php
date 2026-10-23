<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Routine;

use MySqlMemory\Instance;
use MySqlMemory\Program\Routine\SelectionNotices;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(SelectionNotices::class)]
#[Medium]
final class SelectionNoticesTest extends TestCase
{
    public function testStartConsumesInitialNoticesAndRetainsUnchangedRelationFailures(): void
    {
        $session = (new Instance())->connect();
        $selection = new M\Routine\Program\DeferredSelection([new M\Routine\Program\DeferredProjection(new M\Routine\Program\Expression\Conversion\DeferredBinaryCast(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')])))]);
        $derivation = new \SqlSemantics\Construction\Derivation($session->semantics()->context([]));
        $selection->deriveProgram($derivation, new \SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope($derivation->environment()));
        $notices = new SelectionNotices();
        $notices->start($selection, $derivation->facts(), $session);
        $notices->start($selection, $derivation->facts(), $session);
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testFailedPreservesTheNoticeOnTheNextPreparation(): void
    {
        $session = (new Instance())->connect();
        $selection = new M\Routine\Program\DeferredSelection([new M\Routine\Program\DeferredProjection(new M\Routine\Program\Expression\Conversion\DeferredBinaryCast(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')])))]);
        $derivation = new \SqlSemantics\Construction\Derivation($session->semantics()->context([]));
        $selection->deriveProgram($derivation, new \SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope($derivation->environment()));
        $notices = new SelectionNotices();
        $notices->start($selection, $derivation->facts(), $session);
        $notices->failed($selection, $session);
        $notices->start($selection, $derivation->facts(), $session);
        self::assertSame([1287], array_column($session->diagnostics->conditions, 1));
    }
}
