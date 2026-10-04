<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowRows;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(ShowRows::class)]
#[Small]
final class ShowRowsTest extends TestCase
{
    public function testColumnsAnswersEveryLayoutOfEveryRelease(): void
    {
        $rows = new ShowRows();
        self::assertSame([['Log_name', 'v'], ['File_size', 'B']], $rows->columns(Report::BinaryLogs, GrammarRelease::MySql5651));
        self::assertSame(['Log_name', 'v'], $rows->columns(Report::BinaryLogs, GrammarRelease::MySql910)[0]);
        self::assertCount(13, $rows->columns(Report::Keys, GrammarRelease::MySql5744));
    }

    public function testColumnsRefusesAReleaseWithoutTheLayout(): void
    {
        $this->expectExceptionMessage('The release mysql-5.6.51 has no create_user layout.');
        (new ShowRows())->columns(Report::CreateUser, GrammarRelease::MySql5651);
    }

    public function testSlotsDecodeTheCodes(): void
    {
        $slots = (new ShowRows())->slots([['a', 'B?'], ['b', 'n']]);
        self::assertSame(['a', Nullability::Nullable, 'b', Nullability::Nullable], [$slots[0]->name?->value, $slots[0]->nullability, $slots[1]->name?->value, $slots[1]->nullability]);
        self::assertInstanceOf(NullOnly::class, $slots[1]->type);
    }

    public function testTypeDecodesEveryCode(): void
    {
        $rows = new ShowRows();
        self::assertEquals(new Known(new Character(CharacterKind::LongText)), $rows->type('l'));
        self::assertEquals(new Known(new Temporal(TemporalKind::Timestamp)), $rows->type('T'));
        self::assertEquals(new Known(new Integral(IntegralKind::Int, null, [NumericModifier::Unsigned])), $rows->type('I'));
        self::assertEquals(new Known(new Floating(FloatingKind::Double, null, null, [NumericModifier::Unsigned])), $rows->type('F'));
    }
}
