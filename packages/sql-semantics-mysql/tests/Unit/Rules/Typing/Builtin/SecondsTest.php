<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Builtin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Seconds;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(Seconds::class)]
#[Small]
final class SecondsTest extends TestCase
{
    public function testDigitsAnswersTheFractionalDigitsOfEachKind(): void
    {
        $call = new Invocation([Domain::integer(), Domain::decimal(10, 3), Domain::double(), new Domain(Kind::DateTime, Field::DateTime, 23, 3), new Domain(Kind::Date, Field::Date, 10), Domain::string(5, Collation::known('utf8mb4_0900_ai_ci'))], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame([0, 3, 6, 3, 0, 6], array_map(static fn (int $index): int => (new Seconds())->digits($call, $index, false), [0, 1, 2, 3, 4, 5]));
    }

    public function testLiteralReadsTheDigitsAStringLiteralWrites(): void
    {
        $call = new Invocation([Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'))], [new StringLiteral(['12:00:00.25'])], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame(2, (new Seconds())->literal($call, 0, true));
    }

    public function testWrittenReadsADateAndTimeOrATime(): void
    {
        $seconds = new Seconds();

        self::assertSame([3, 0, 0, null, null, 1, 2, null, 0], [
            $seconds->written('2024-01-01 10:00:00.123', false), $seconds->written('12:00:00.5', false), $seconds->written('2024.01.01.5', false), $seconds->written('x', false), $seconds->written('2024-02-30', false),
            $seconds->written('12:00:00.5', true), $seconds->written('2024.01.01', true), $seconds->written('25:61:00.5', true), $seconds->written('2024-01-01T10:00:00.5', true),
        ]);
    }

    public function testValidChecksTheRangesAndTheDayOfTheMonth(): void
    {
        self::assertSame([true, false, true, false], [(new Seconds())->valid('2024-02-29 10:00:00'), (new Seconds())->valid('2023-02-29'), (new Seconds())->valid('2024-00-00'), (new Seconds())->valid('2024-01-01 24:00:00')]);
    }
}
