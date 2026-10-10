<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Typing\Variables;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(Variables::class)]
#[Small]
final class VariablesTest extends TestCase
{
    public function testHeldUsesTheLegacyIntegerVariableWidthInMySql56(): void
    {
        $variables = new Variables(new Settings(Collation::known('utf8mb4_general_ci')), \SqlSemantics\Contract\GrammarRelease::MySql5651);

        self::assertEquals(Domain::integer(Field::LongLong, 20), $variables->held(Domain::integer(Field::Long, 11)));
    }

    public function testReadNeedsTheVariablesOfTheSession(): void
    {
        $known = new Variables(new Settings(Collation::known('utf8mb4_0900_ai_ci'), 4, null, [], 1024, ['A' => Domain::decimal(3, 1)]));

        self::assertNull((new Variables(new Settings(Collation::known('utf8mb4_0900_ai_ci'))))->read('a'));
        self::assertEquals(Domain::decimal(65, 30), $known->read('a'));
        self::assertEquals(Domain::string(65532, Collation::binary()), $known->read('never'));
    }

    public function testHeldReadsNumbersWideAndEverythingElseAsABlob(): void
    {
        $variables = new Variables(new Settings(Collation::known('utf8mb4_0900_ai_ci')));

        self::assertEquals(Domain::integer(Field::LongLong, 21, true), $variables->held(Domain::integer(Field::Long, 10, true)));
        self::assertEquals(Domain::double(23), $variables->held(Domain::double(5, 2)));
        self::assertEquals(Domain::string(16777215, Collation::binary(), Field::MediumBlob), $variables->held(Domain::null()));
        self::assertSame('latin1_swedish_ci', $variables->held(new Domain(Kind::Date, Field::Date, 10))->collation->name);
    }

    public function testTextIsALongBlobForAMultibyteCharacterSet(): void
    {
        $variables = new Variables(new Settings(Collation::known('utf8mb4_0900_ai_ci')));

        self::assertSame(Field::LongBlob, $variables->text(Collation::known('utf8mb4_bin'))->field);
        self::assertSame(Field::MediumBlob, $variables->text(Collation::known('latin1_bin'))->field);
    }

    public function testTextCountsTheBytesOfTheMostBytesInTheFewestFromMySql80(): void
    {
        $variables = new Variables(new Settings(Collation::known('utf8mb4_0900_ai_ci')));

        self::assertSame([67108860, 16777215, 16777215], [$variables->text(Collation::known('utf8mb4_bin'))->length, $variables->text(Collation::known('ucs2_general_ci'))->length, (new Variables(new Settings(Collation::known('utf8mb4_0900_ai_ci')), \SqlSemantics\Contract\GrammarRelease::MySql5744))->text(Collation::known('utf8mb4_bin'))->length]);
    }
}
