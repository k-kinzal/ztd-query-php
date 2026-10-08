<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Hint\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\StrategyHint;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;

#[CoversClass(StrategyHint::class)]
#[Small]
final class StrategyHintTest extends TestCase
{
    public function testNameAnswersTheHint(): void
    {
        self::assertSame(HintName::Subquery, (new StrategyHint(HintName::Subquery, null, ['INTOEXISTS']))->name());
    }

    public function testTextWritesTheBlockAndTheStrategies(): void
    {
        self::assertSame('NO_SEMIJOIN()', (new StrategyHint(HintName::NoSemijoin, null, []))->text());
        self::assertSame('SEMIJOIN(@`q`)', (new StrategyHint(HintName::Semijoin, 'q', []))->text());
        self::assertSame('SUBQUERY(MATERIALIZATION)', (new StrategyHint(HintName::Subquery, null, ['MATERIALIZATION']))->text());
    }

    public function testSubqueryNamesOneStrategy(): void
    {
        $this->expectExceptionMessage('SUBQUERY names one strategy.');

        new StrategyHint(HintName::Subquery, null, []);
    }

    public function testAStrategyOfAnotherHintIsRefused(): void
    {
        $this->expectExceptionMessage('A strategy of a subquery hint is one its hint takes.');

        new StrategyHint(HintName::Subquery, null, ['FIRSTMATCH']);
    }
}
