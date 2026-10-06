<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\TemporalMerge;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;

#[CoversClass(TemporalMerge::class)]
#[Small]
final class TemporalMergeTest extends TestCase
{
    public function testMergeWidensTemporalTypesAndMergesYearWithIntegers(): void
    {
        $merge = new TemporalMerge();

        self::assertEquals(
            [new Temporal(TemporalKind::Timestamp), new Temporal(TemporalKind::DateTime), new Integral(IntegralKind::SmallInt), new Character(CharacterKind::VarChar), new Character(CharacterKind::VarChar)],
            [
                $merge->merge(new Temporal(TemporalKind::Timestamp, '3'), new Temporal(TemporalKind::Timestamp)),
                $merge->merge(new Temporal(TemporalKind::Time), new Temporal(TemporalKind::Date)),
                $merge->merge(new Temporal(TemporalKind::Year), new Integral(IntegralKind::SmallInt)),
                $merge->merge(new Temporal(TemporalKind::Year), new Temporal(TemporalKind::Date)),
                $merge->merge(new Temporal(TemporalKind::Date), new Integral(IntegralKind::Int)),
            ],
        );
    }
}
