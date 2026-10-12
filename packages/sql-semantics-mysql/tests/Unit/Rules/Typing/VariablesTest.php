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
        self::assertEquals(new Domain(Kind::String, Field::LongBlob, 1073741823, Domain::NOT_FIXED, collation: Collation::known('utf8mb4_bin'), display: 4294967292), $variables->assignment(new Domain(Kind::Json, Field::Json, 4294967295)));
    }

    public function testAssignmentTextRetainsCharactersUntilTheVarcharByteBoundIsExceeded(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $variables = new Variables(new Settings($collation));

        self::assertEquals(Domain::string(255, $collation), $variables->assignmentText(Domain::string(255, $collation, Field::TinyBlob)));
        self::assertEquals(new Domain(Kind::String, Field::MediumBlob, 65535, Domain::NOT_FIXED, collation: $collation, display: 262140), $variables->assignmentText(Domain::string(65535, $collation, Field::Blob)));
        self::assertEquals(new Domain(Kind::String, Field::LongBlob, 16777215, Domain::NOT_FIXED, collation: $collation, display: 67108860), $variables->assignmentText(Domain::string(16777215, $collation, Field::MediumBlob)));
    }

    #[DataProvider('providerLegacyReleases')]
    public function testAssignmentRetainsLegacyOperandWidthsScalesAndImplicitCoercibility(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $collation = Collation::known('latin1_swedish_ci');
        $variables = new Variables(new Settings($collation), $release);

        self::assertEquals(new Domain(Kind::Integer, Field::LongLong, 4), $variables->assignment(Domain::integer(Field::Tiny, 4)));
        self::assertEquals(new Domain(Kind::Double, Field::Double, 8, 2), $variables->assignment(new Domain(Kind::Double, Field::Float, 8, 2)));
        self::assertEquals(new Domain(Kind::Decimal, Field::NewDecimal, 8, 2, true), $variables->assignment(Domain::decimal(7, 2, true)));
        self::assertEquals(new Domain(Kind::String, Field::VarString, 10, 0, collation: $collation, displayDecimals: 0), $variables->assignment(new Domain(Kind::Date, Field::Date, 10)));
        self::assertEquals(new Domain(Kind::String, Field::VarString, 0, displayDecimals: 0), $variables->assignment(Domain::null()));
        self::assertEquals(new Domain(Kind::String, Field::LongBlob, 1073741823, Domain::NOT_FIXED, collation: Collation::known('utf8mb4_bin'), display: 4294967292), $variables->assignment(new Domain(Kind::Json, Field::Json, 4294967295)));
    }

    #[DataProvider('providerLegacyReleases')]
    public function testAssignmentExpandsLegacyIntegerMetadataWithoutChangingItsLogicalWidth(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $variables = new Variables(new Settings(Collation::known('utf8mb4_general_ci')), $release);
        $assigned = $variables->assignment(Domain::integer(Field::Long, 11));

        self::assertSame(11, $assigned->value()->length);
        self::assertSame(44, $assigned->metadataLength());
        self::assertEquals($assigned, $variables->assignment($assigned->value()));
    }

    public function testAssignmentDisplaySeparatesIntegerAndDecimalMetadataFromStringBounds(): void
    {
        $variables = new Variables(new Settings(Collation::known('utf8mb4_general_ci')), \SqlSemantics\Contract\GrammarRelease::MySql5744);

        self::assertSame(84, $variables->assignmentDisplay(Domain::integer()));
        self::assertSame(332, $variables->assignmentDisplay($variables->held(Domain::decimal(7, 2))));
        self::assertNull($variables->assignmentDisplay(Domain::string(3, Collation::binary())));
    }

    #[DataProvider('providerLegacyReleases')]
    public function testAssignedInheritsAnIntegerVariableWidthAcrossConnectionChanges(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $variables = new Variables(new Settings(Collation::known('utf8mb4_general_ci')), $release);
        $singleByte = $variables->held(Domain::integer(), Collation::known('latin1_swedish_ci'));
        $assigned = $variables->assigned(new \SqlSemantics\Statement\Type\Known($singleByte));

        self::assertInstanceOf(\SqlSemantics\Statement\Type\Known::class, $assigned);
        self::assertInstanceOf(Domain::class, $assigned->descriptor);
        self::assertSame(20, $assigned->descriptor->metadataLength());
        self::assertSame(20, $assigned->descriptor->value()->length);
        self::assertSame(80, (new Variables(new Settings(Collation::known('latin1_swedish_ci')), $release))->assignment($variables->held(Domain::integer()))->metadataLength());
    }

    #[DataProvider('providerLegacyReleases')]
    public function testAssignmentRetainsAnInheritedDoubleMetadataWidth(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $variables = new Variables(new Settings(Collation::known('latin1_swedish_ci')), $release);
        $source = $variables->held(Domain::double(), Collation::known('utf8mb4_general_ci'));
        $assigned = $variables->assignment($source);

        self::assertSame(92, $assigned->metadataLength());
        self::assertSame(23, $assigned->value()->length);
        self::assertSame(Domain::NOT_FIXED, $assigned->decimals);
    }

    #[DataProvider('providerLegacyReleases')]
    public function testAssignmentPreservesReportedStringScaleAcrossNestedAssignments(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $variables = new Variables(new Settings(Collation::known('latin1_swedish_ci')), $release);
        $literal = new Domain(Kind::String, Field::VarString, 1, coercibility: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Coercible);
        $assigned = $variables->assignment($literal);

        self::assertSame(0, $assigned->displayDecimals);
        self::assertEquals($assigned, $variables->assignment($assigned));
        self::assertSame(Domain::NOT_FIXED, $variables->assignment(new Domain(Kind::String, Field::Blob, 65535))->decimals);
    }

    #[DataProvider('providerLegacyReleases')]
    public function testHeldSeparatesLegacyDecimalMetadataWidthFromExpressionPrecision(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $domain = (new Variables(new Settings(Collation::known('latin1_swedish_ci')), $release))->held(Domain::decimal(7, 2));

        self::assertSame(83, $domain->metadataLength());
        self::assertSame(65, $domain->precision());
        self::assertSame(30, $domain->decimals);
        self::assertSame($domain, $domain->value());
    }

    #[DataProvider('providerLegacyReleases')]
    public function testHeldExpandsLegacyDecimalAndDoubleMetadataUsingTheAssignedCharacterSet(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $variables = new Variables(new Settings(Collation::known('latin1_swedish_ci')), $release);
        $assigned = Collation::known('utf8mb4_general_ci');
        $decimal = $variables->held(Domain::decimal(5, 2), $assigned);
        $double = $variables->held(Domain::double(), $assigned);

        self::assertSame(332, $decimal->metadataLength());
        self::assertSame(65, $decimal->precision());
        self::assertSame(30, $decimal->decimals);
        self::assertSame(92, $double->metadataLength());
        self::assertSame(23, $double->value()->length);
    }

    #[DataProvider('providerLegacyReleases')]
    public function testTextKeepsMultibyteLegacyVariablesInAMediumBlob(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $collation = Collation::known('utf8mb4_bin');

        self::assertEquals(Domain::string(16777215, $collation, Field::MediumBlob), (new Variables(new Settings($collation), $release))->text($collation));
    }

    #[DataProvider('providerLegacyReleases')]
    public function testAssignmentRetainsTheDecimalReadWidthWithoutChangingPrecision(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $variables = new Variables(new Settings(Collation::known('latin1_swedish_ci')), $release);
        $result = $variables->assignment($variables->held(Domain::decimal(7, 2))->value());

        self::assertSame(83, $result->metadataLength());
        self::assertSame(65, $result->precision());
        self::assertSame(30, $result->decimals);
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

    public function testAllocationSeparatesOperandCollationsFromNumericValueCollations(): void
    {
        $connection = Collation::known('utf8mb4_0900_ai_ci');
        $latin1 = Collation::known('latin1_swedish_ci');
        $variables = new Variables(new Settings($connection));

        self::assertSame($connection, $variables->allocation(Domain::integer()));
        self::assertSame($connection, $variables->allocation(new Domain(Kind::Date, Field::Date, 10)));
        self::assertSame($latin1, $variables->allocation(Domain::string(3, $latin1)));
        self::assertEquals(Collation::binary(), $variables->allocation(Domain::null()));
    }

    public function testIntroduceUsesTheAssignedStringCollationForLaterOccurrences(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $settings = new Settings(Collation::known('utf8mb4_0900_ai_ci'), userVariables: []);
        $query = $semantics->analyze("SELECT @v:=_latin1'a',@v", $semantics->context([], session: $settings));

        self::assertEquals(new \SqlSemantics\Statement\Type\Known(Domain::string(16777215, Collation::known('latin1_swedish_ci'), Field::MediumBlob)), $query->field(1)->type);
        self::assertSame([], $settings->userVariables);
    }

    public function testAllocationRetainsAVariableEntryCollationThroughGroupingAndUnaryPlus(): void
    {
        $latin1 = Collation::known('latin1_swedish_ci');
        $variables = new Variables(new Settings(Collation::known('utf8mb4_0900_ai_ci'), userVariables: ['source' => new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry(Domain::integer(), $latin1)]));
        $source = new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('SOURCE'));
        $grouped = new \SqlSemantics\Platform\MySql\Statement\Expression\Grouped(new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Plus, $source));

        self::assertSame($latin1, $variables->allocation(Domain::integer(), $grouped));
    }

    public function testIntroduceCopiesTheSourceEntryCollationBeforeExecution(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $latin1 = Collation::known('latin1_swedish_ci');
        $source = new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry(Domain::integer(), $latin1);
        $settings = new Settings(Collation::known('utf8mb4_0900_ai_ci'), userVariables: ['source' => $source]);
        $query = $semantics->analyze('SELECT @copy:=@source,@copy', $semantics->context([], session: $settings));

        self::assertEquals(new \SqlSemantics\Statement\Type\Known(Domain::string(16777215, $latin1, Field::MediumBlob)), $query->field(1)->type);
        self::assertSame(['source' => $source], $settings->userVariables);
    }

    public function testHeldUsesTheLegacyIntegerVariableWidthInMySql56(): void
    {
        $variables = new Variables(new Settings(Collation::known('utf8mb4_general_ci')), \SqlSemantics\Contract\GrammarRelease::MySql5651);

        $domain = $variables->held(Domain::integer(Field::Long, 11));
        self::assertEquals(Domain::integer(Field::LongLong, 20), $domain->value());
        self::assertSame(80, $domain->metadataLength());
    }

    public function testReadNeedsTheVariablesOfTheSession(): void
    {
        $known = new Variables(new Settings(Collation::known('utf8mb4_0900_ai_ci'), 4, null, [], 1024, ['A' => Domain::decimal(3, 1)]));

        self::assertNull((new Variables(new Settings(Collation::known('utf8mb4_0900_ai_ci'))))->read('a'));
        self::assertEquals(Domain::decimal(65, 30), $known->read('a'));
        self::assertEquals(Domain::string(65532, Collation::binary()), $known->read('never'));
    }

    #[DataProvider('providerLegacyReleases')]
    public function testReadUsesTheAssignedCharacterSetAfterTheConnectionChanges(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $latin1 = Collation::known('latin1_swedish_ci');
        $utf8 = Collation::known('utf8mb4_general_ci');
        $stored = new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry(Domain::integer(), $utf8);
        $variables = new Variables(new Settings($latin1, userVariables: ['V' => $stored]), $release);
        $domain = $variables->read('v');

        self::assertSame(80, $domain?->metadataLength());
        self::assertSame(20, $domain->value()->length);
        self::assertSame('binary', $domain->collation->name);
        self::assertSame(20, (new Variables(new Settings($utf8, userVariables: ['v' => new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry(Domain::integer(), $latin1)]), $release))->read('v')?->metadataLength());
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

        $domain = $variables->held(Domain::integer(Field::Long, 11));
        self::assertEquals(Domain::integer(Field::LongLong, 20), $domain->value());
        self::assertSame(80, $domain->metadataLength());
    }

}
