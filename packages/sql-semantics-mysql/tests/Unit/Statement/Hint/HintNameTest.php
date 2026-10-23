<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Hint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Hint\HintForm;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;

#[CoversClass(HintName::class)]
#[Small]
final class HintNameTest extends TestCase
{
    public function testFormAnswersTheArgumentsOfEachHint(): void
    {
        self::assertSame(HintForm::Table, HintName::DerivedConditionPushdown->form());
        self::assertSame(HintForm::JoinOrder, HintName::JoinSuffix->form());
        self::assertSame(HintForm::FixedOrder, HintName::JoinFixedOrder->form());
        self::assertSame(HintForm::Key, HintName::NoSkipScan->form());
        self::assertSame(HintForm::Semijoin, HintName::NoSemijoin->form());
        self::assertSame(HintForm::Subquery, HintName::Subquery->form());
        self::assertSame(HintForm::ExecutionTime, HintName::MaxExecutionTime->form());
        self::assertSame(HintForm::ResourceGroup, HintName::ResourceGroup->form());
        self::assertSame(HintForm::Variable, HintName::SetVar->form());
        self::assertSame(HintForm::BlockName, HintName::QbName->form());
    }

    public function testAvailableAnswersTheHintsOfEachRelease(): void
    {
        self::assertSame([], array_values(array_filter(HintName::cases(), static fn (HintName $name): bool => $name->available(GrammarRelease::MySql5651))));
        self::assertSame(['BKA', 'NO_BKA', 'BNL', 'NO_BNL', 'MRR', 'NO_MRR', 'NO_ICP', 'NO_RANGE_OPTIMIZATION', 'SEMIJOIN', 'NO_SEMIJOIN', 'SUBQUERY', 'MAX_EXECUTION_TIME', 'QB_NAME'], array_column(array_values(array_filter(HintName::cases(), static fn (HintName $name): bool => $name->available(GrammarRelease::MySql5744))), 'value'));
        self::assertCount(37, array_filter(HintName::cases(), static fn (HintName $name): bool => $name->available(GrammarRelease::MySql847)));
    }
}
