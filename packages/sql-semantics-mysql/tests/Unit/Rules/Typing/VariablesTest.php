<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function testAssignedResolvesDeclaredIntegersAndNullWithoutReplacingMissingTypes(): void
    {
        $variables = new Variables(new Settings(Collation::known('utf8mb4_0900_ai_ci')));
        $integer = new \SqlSemantics\Statement\Type\Known(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int));
        $missing = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT missing')->field(0)->type;

        self::assertEquals(new \SqlSemantics\Statement\Type\Known(Domain::integer(Field::LongLong, 11)), $variables->assigned($integer));
        self::assertEquals(new \SqlSemantics\Statement\Type\Known(Domain::string(0, Collation::binary())), $variables->assigned(new \SqlSemantics\Statement\Type\NullOnly()));
        self::assertSame($missing, $variables->assigned($missing));
    }

    public function testAssignmentSeparatesTheResultFromTheStoredVariableType(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $variables = new Variables(new Settings($collation));

        self::assertEquals(Domain::integer(Field::LongLong, 4), $variables->assignment(Domain::integer(Field::Tiny, 4)));
        self::assertEquals(Domain::double(23), $variables->assignment(new Domain(Kind::Double, Field::Float, 8, 2)));
        self::assertEquals(Domain::decimal(7, 2), $variables->assignment(Domain::decimal(7, 2)));
        self::assertEquals(Domain::decimal(7, 2), $variables->assignment(Domain::decimal(7, 2, true)));
        self::assertEquals(Domain::string(10, $collation), $variables->assignment(new Domain(Kind::Date, Field::Date, 10)));
        self::assertEquals(Domain::string(4294967295, Collation::known('utf8mb4_bin'), Field::LongBlob), $variables->assignment(new Domain(Kind::Json, Field::Json, 4294967295)));
    }

    public function testAssignmentTextRetainsCharactersUntilTheVarcharByteBoundIsExceeded(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $variables = new Variables(new Settings($collation));

        self::assertEquals(Domain::string(255, $collation), $variables->assignmentText(Domain::string(255, $collation, Field::TinyBlob)));
        self::assertEquals(Domain::string(262140, $collation, Field::MediumBlob), $variables->assignmentText(Domain::string(65535, $collation, Field::Blob)));
        self::assertEquals(Domain::string(67108860, $collation, Field::LongBlob), $variables->assignmentText(Domain::string(16777215, $collation, Field::MediumBlob)));
    }

    public function testInferredRetainsTheBranchCollationAndTemporalTextWidths(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $variables = new Variables(new Settings($collation));

        self::assertEquals(Domain::string(16383, $collation), $variables->inferred(Domain::string(1, $collation)));
        self::assertEquals(Domain::decimal(65, 30), $variables->inferred(Domain::decimal(2, 1)));
        self::assertSame([10, 15, 26], [$variables->inferred(new Domain(Kind::Date, Field::Date, 10))->length, $variables->inferred(new Domain(Kind::Time, Field::Time, 10))->length, $variables->inferred(new Domain(Kind::DateTime, Field::DateTime, 19))->length]);
        self::assertEquals(Domain::string(65535, Collation::binary()), $variables->inferred(Domain::null()));
        self::assertEquals(Domain::string(16383, Collation::known('utf8mb4_bin')), $variables->inferred(new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin'))));
    }

    public function testIntroduceAllocatesAnEntryWithoutChangingTheSession(): void
    {
        $settings = new Settings(Collation::known('latin1_swedish_ci'), userVariables: []);
        $derivation = new \SqlSemantics\Construction\Derivation((new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->context([], session: $settings));
        (new Variables($settings))->introduce('V', $derivation);

        self::assertEquals(new \SqlSemantics\Statement\Type\Known(Domain::string(16777215, $settings->connection, Field::MediumBlob)), $derivation->introducedVariables['v']);
        self::assertSame([], $settings->userVariables);
    }

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

    /**
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease}>
     */
    public static function providerLegacyReleases(): iterable
    {
        yield '5.6' => [\SqlSemantics\Contract\GrammarRelease::MySql5651];
        yield '5.7' => [\SqlSemantics\Contract\GrammarRelease::MySql5744];
    }

    #[DataProvider('providerLegacyReleases')]
    public function testReadKeepsAnAbsentLegacyVariableAsALongBlob(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $variables = new Variables(new Settings(Collation::known('latin1_swedish_ci'), userVariables: []), $release);

        self::assertEquals(Domain::string(16777216, Collation::binary(), Field::LongBlob), $variables->read('v'));
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

    public function testTextCountsTheMaximumBytesPerCharacterFromMySql80(): void
    {
        $variables = new Variables(new Settings(Collation::known('utf8mb4_0900_ai_ci')));

        self::assertSame([67108860, 33554430, 16777215], [$variables->text(Collation::known('utf8mb4_bin'))->length, $variables->text(Collation::known('ucs2_general_ci'))->length, (new Variables(new Settings(Collation::known('utf8mb4_0900_ai_ci')), \SqlSemantics\Contract\GrammarRelease::MySql5744))->text(Collation::known('utf8mb4_bin'))->length]);
    }
    public function testHeldKeepsMySql57IntegerVariableWidth(): void
    {
        $variables = new Variables(new Settings(Collation::known('utf8mb4_general_ci')), \SqlSemantics\Contract\GrammarRelease::MySql5744);

        self::assertEquals(Domain::integer(Field::LongLong, 20), $variables->held(Domain::integer(Field::Long, 11)));
    }

}
