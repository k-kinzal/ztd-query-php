<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\ClearSequenceOwner;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceChanges;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceResetOperand;

#[CoversClass(SequenceChanges::class)]
final class SequenceChangesTest extends TestCase
{
    public function testToStringDistinguishesOmissionFromExplicitDefaultAndClearing(): void
    {
        $value = new SequenceChanges(
            minimum: SequenceResetOperand::Native,
            start: new WideIntegerLiteral('4'),
            restart: SequenceResetOperand::Native,
            cycle: false,
            owner: ClearSequenceOwner::Remove,
        );

        self::assertSame(' NO MINVALUE START WITH 4 RESTART NO CYCLE OWNED BY NONE', $value->toString());
    }

    public function testRejectsASetWithoutAssignments(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A sequence change requires at least one assignment.');

        new SequenceChanges();
    }

    public function testRejectsZeroIncrement(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A sequence increment cannot be zero.');

        new SequenceChanges(increment: new WideIntegerLiteral('0'));
    }
}
