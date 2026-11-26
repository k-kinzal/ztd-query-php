<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceChangeRules;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceResetOperand;

#[CoversClass(SequenceChangeRules::class)]
final class SequenceChangeRulesTest extends TestCase
{
    public function testBoundKeepsTheResetRole(): void
    {
        self::assertSame(' NO MAXVALUE', SequenceChangeRules::bound('MAXVALUE', SequenceResetOperand::Native));
        self::assertSame('', SequenceChangeRules::bound('MAXVALUE', null));
    }

    public function testCheckLimitsRejectsAStaticallyImpossibleRestart(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('The sequence start and restart must be within its limits.');

        SequenceChangeRules::checkLimits(IntegerStorage::Small, null, new WideIntegerLiteral('0'), null, null, new WideIntegerLiteral('-1'));
    }
}
