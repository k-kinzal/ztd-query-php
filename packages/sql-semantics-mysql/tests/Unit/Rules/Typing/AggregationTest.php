<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Aggregation;
use SqlSemantics\Platform\MySql\Rules\Typing\Collations;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Aggregation::class)]
#[Small]
final class AggregationTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerCoercibleTemporalControls(): iterable
    {
        yield 'MySQL 5.6' => ['mysql-5.6.51'];
        yield 'MySQL 5.7' => ['mysql-5.7.44'];
        yield 'MySQL 8.0' => ['mysql-8.0.44'];
    }

    #[DataProvider('providerCoercibleTemporalControls')]
    public function testTemporalsPreserveTheCoercibleStrengthOfOlderControls(string $release): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $query = $semantics->analyze("SELECT COALESCE(CAST('2020-01-02' AS DATE),CAST('2020-01-03' AS DATE))");
        $type = $query->field(0)->type;

        self::assertInstanceOf(\SqlSemantics\Statement\Type\Known::class, $type);
        self::assertInstanceOf(Domain::class, $type->descriptor);
        self::assertSame(Coercibility::Coercible, $type->descriptor->coercibility);
    }

    public function testFixedPreservesCharUntilAVariableStringParticipates(): void
    {
        $collation = Collation::known('utf8mb4_bin');
        $rules = new Aggregation(new Collations($collation));
        $result = Domain::string(3, $collation);
        $character = Domain::string(3, $collation, Field::String);

        self::assertSame(Field::String, $rules->fixed($result, [$character, Domain::integer()])->field);
        self::assertSame($result, $rules->fixed($result, [$character, $result]));
        self::assertSame($result, $rules->fixed($result, [Domain::integer()]));
    }

    public function testLegacyLengthKeepsCoalesceFloatingBounds(): void
    {
        $rules = new Aggregation(new Collations(Collation::known('latin1_swedish_ci')));
        $branches = [Domain::string(3, Collation::known('latin1_swedish_ci'), Field::String), Domain::double(3)];

        self::assertSame(3, $rules->legacyLength($branches, 'coalesce'));
        self::assertSame(3, $rules->legacyLength($branches, 'ifnull'));
        self::assertSame(3, $rules->legacyLength($branches, 'case'));
        self::assertSame(3, $rules->legacyLength($branches, 'if'));
        self::assertSame(22, $rules->legacyLength($branches, 'UNION'));
        self::assertSame(10, $rules->legacyLength([$branches[0], new Domain(Kind::Date, Field::Date, 10)], 'coalesce'));
    }

    public function testStringsRetainTheLegacyCoalesceFloatingWidth(): void
    {
        $collation = Collation::known('latin1_swedish_ci');
        $rules = new Aggregation(new Collations($collation));
        $context = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]));
        $result = $rules->strings([Domain::string(3, $collation, Field::String), Domain::double(3)], 'coalesce', $context);

        self::assertInstanceOf(Domain::class, $result);
        self::assertSame([Field::String, 3], [$result->field, $result->length]);
    }

    public function testSizedKeepsTheReleaseSpecificFieldOfLongStringBranches(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $aggregation = new Aggregation(new Collations($collation));

        self::assertEquals(Domain::string(67108860, $collation, Field::LongBlob), $aggregation->sized(67108860, $collation, Coercibility::Implicit));
        self::assertSame(67108860, $aggregation->sized(67108860, $collation, Coercibility::Implicit, true)->metadataLength());
    }

    public function testOfSkipsNullBranches(): void
    {
        self::assertEquals(Domain::integer(Field::LongLong, 2), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->of([Domain::null(), Domain::integer(Field::LongLong, 2)], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
        self::assertEquals(Domain::null(), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->of([Domain::null()], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
        self::assertEquals(Domain::double(23), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->of([Domain::integer(), Domain::double()], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
    }

    public function testIntegersKeepTheWiderSignedType(): void
    {
        self::assertEquals(Domain::integer(Field::Short, 6), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->integers([Domain::integer(Field::Tiny, 3, true), Domain::integer(Field::Short, 6)]));
    }

    /**
     * @return iterable<string, array{GrammarRelease, string, int, int}>
     */
    public static function providerIntegerBranchBounds(): iterable
    {
        yield 'MySQL 5.6 single-digit CASE' => [GrammarRelease::MySql5651, 'case', 1, 0];
        yield 'MySQL 5.6 wider CASE' => [GrammarRelease::MySql5651, 'case', 3, 3];
        yield 'MySQL 5.6 signed CASE' => [GrammarRelease::MySql5651, 'case', 4, 4];
        yield 'MySQL 5.6 IF' => [GrammarRelease::MySql5651, 'if', 1, 1];
        yield 'MySQL 5.6 COALESCE' => [GrammarRelease::MySql5651, 'coalesce', 1, 1];
        yield 'MySQL 5.7 CASE' => [GrammarRelease::MySql5744, 'case', 1, 1];
        yield 'MySQL 8.4 CASE' => [GrammarRelease::MySql847, 'case', 1, 1];
    }

    #[DataProvider('providerIntegerBranchBounds')]
    public function testIntegersSeparateCaseMetadataFromTheValueBound(GrammarRelease $release, string $operation, int $length, int $reported): void
    {
        $aggregation = new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')));
        $result = $aggregation->integers([Domain::integer(Field::LongLong, $length)], $operation, $release);

        self::assertSame($length, $result->length);
        self::assertSame($reported, $result->metadataLength());
    }

    public function testOfUsesTheLegacyCaseWidthWithoutChangingOtherIntegerBranches(): void
    {
        $aggregation = new Aggregation(new Collations(Collation::known('utf8mb4_general_ci')));
        $result = $aggregation->of([Domain::integer(Field::LongLong, 1)], 'case', new Derivation((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context([])));

        self::assertInstanceOf(Domain::class, $result);
        self::assertSame([1, 0], [$result->length, $result->metadataLength()]);
    }

    public function testIntegersWidenWhenSignedMeetsUnsigned(): void
    {
        self::assertEquals(Domain::integer(Field::Long, 11), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->integers([Domain::integer(Field::Tiny, 4), Domain::integer(Field::Long, 11)]));
        self::assertEquals(Domain::integer(Field::LongLong, 10), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->integers([Domain::integer(Field::Long, 10, true), Domain::integer(Field::Short, 6)]));
        self::assertEquals(Domain::decimal(20, 0), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->integers([Domain::integer(Field::LongLong, 20, true), Domain::integer(Field::Tiny, 4)]));
    }

    public function testDecimalsHoldTheIntegralAndFractionalDigitsOfEach(): void
    {
        self::assertEquals(Domain::decimal(2, 1), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->decimals([Domain::integer(Field::LongLong, 2), Domain::decimal(2, 1)]));
    }

    public function testTemporalsMakeADatetimeOfADateAndADatetime(): void
    {
        $date = new Domain(Kind::Date, Field::Date, 10);
        $datetime = new Domain(Kind::DateTime, Field::DateTime, 23, 3);

        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 23, 3, false, Collation::known('latin1_swedish_ci')), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->temporals([$date, $datetime], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 23, 3, false, Collation::known('utf8mb4_0900_ai_ci'), [], Coercibility::Coercible), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->temporals([$date, $datetime], 'case', new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]))));
    }

    public function testTemporalsMakeADatetimeOfATimeFromMySql80(): void
    {
        $date = new Domain(Kind::Date, Field::Date, 10);
        $time = new Domain(Kind::Time, Field::Time, 12, 1);

        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 21, 1, false, Collation::known('latin1_swedish_ci')), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->temporals([$date, $time], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
        self::assertSame(Kind::String, (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->temporals([$date, $time], 'case', new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([])))?->kind);
    }

    public function testStringsTakeTheLongestTextInTheSettledCollation(): void
    {
        $literal = Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible);

        self::assertEquals(Domain::string(22, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->strings([$literal, Domain::double()], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
        self::assertSame(12, (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->strings([$literal, new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED)], 'case', new Derivation((new Semantics(Dialect::MySql))->context([])))?->length);
        self::assertSame(12, (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->strings([$literal, new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED)], 'case', new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([])))?->length);
    }

    public function testTextsCountTheBytesOfAStringInABinaryResult(): void
    {
        $text = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));
        $bit = new Domain(Kind::Bit, Field::Bit, 5, 0, true);

        self::assertEquals(Domain::string(40, Collation::binary()), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->texts([$text, $bit], 'ifnull', new Derivation((new Semantics(Dialect::MySql))->context([]))));
    }

    public function testTextsCountTheBytesOfATextOutsideASetOperation(): void
    {
        $text = Domain::string(65535, Collation::known('utf8mb4_0900_ai_ci'), Field::Blob);
        $aggregation = new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')));
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));

        self::assertSame([65535, 262140, 65535], [$aggregation->texts([$text, Domain::integer()], 'ifnull', $derivation)?->length, $aggregation->texts([$text, Domain::integer()], 'ifnull', $derivation)?->metadataLength(), $aggregation->texts([$text, Domain::integer()], 'UNION', $derivation)?->length]);
    }

    public function testTextsMakeALongtextOfJsonAmongOtherValues(): void
    {
        $json = new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED);

        self::assertEquals(Domain::string(4294967295, Collation::known('utf8mb4_bin'), Field::LongBlob), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->texts([$json, Domain::integer()], 'coalesce', new Derivation((new Semantics(Dialect::MySql))->context([]))));
    }

    public function testTextsKeepAComputedBlobInAUnionUntilMaterialization(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $aggregation = new Aggregation(new Collations($collation));
        $text = new Domain(Kind::String, Field::LongBlob, 4096, Domain::NOT_FIXED, false, $collation, [], Coercibility::Implicit, 16384);

        self::assertEquals(Domain::string(4096, $collation, Field::Blob), $aggregation->texts([$text, Domain::string(1, $collation)], 'UNION', new Derivation((new Semantics(Dialect::MySql))->context([]))));
    }

    public function testStringsUseTheLegacyExpressionBoundInAUnion(): void
    {
        $collation = Collation::known('utf8mb4_general_ci');
        $aggregation = new Aggregation(new Collations($collation));
        $text = new Domain(Kind::String, Field::Blob, 1024, 0, false, $collation, [], Coercibility::Implicit, 4096);
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]));

        self::assertEquals(Domain::string(1024, $collation, Field::Blob), $aggregation->strings([$text], 'UNION', $derivation));
        self::assertEquals(new Domain(Kind::String, Field::Blob, 1024, Domain::NOT_FIXED, false, $collation, [], Coercibility::Implicit, 4096), $aggregation->strings([$text], 'ifnull', $derivation));
        $partial = new Domain(Kind::String, Field::Blob, 1024, 0, false, $collation, [], Coercibility::Implicit, 4097);
        self::assertSame(4096, $aggregation->strings([$partial], 'ifnull', $derivation)?->metadataLength());
    }

    public function testTextLengthCountsEachValueWrittenAsText(): void
    {
        $aggregation = new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')));
        $text = Domain::string(65535, Collation::known('utf8mb4_0900_ai_ci'), Field::Blob);
        $string = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([262140, 65535, 40, 22, 12], [
            $aggregation->textLength([$text, Domain::integer()], Collation::known('utf8mb4_0900_ai_ci'), false),
            $aggregation->textLength([$text, Domain::integer()], Collation::known('utf8mb4_0900_ai_ci'), true),
            $aggregation->textLength([$string], Collation::binary(), false),
            $aggregation->textLength([Domain::double(), $string], Collation::known('utf8mb4_0900_ai_ci'), false),
            $aggregation->textLength([new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED), $string], Collation::known('utf8mb4_0900_ai_ci'), false),
        ]);
    }

    public function testOfSettlesAYearAndABitAsIntegers(): void
    {
        $aggregation = new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')));
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $year = new Domain(Kind::Year, Field::Year, 4, 0, true);
        $bit = new Domain(Kind::Bit, Field::Bit, 5, 0, true);

        self::assertEquals([Domain::integer(Field::Tiny, 4), Domain::decimal(10, 0), Domain::integer(Field::LongLong, 5, true)], [$aggregation->of([Domain::integer(Field::Tiny, 4), $year], 'ifnull', $derivation), $aggregation->of([Domain::integer(Field::Long, 11), $bit], 'ifnull', $derivation), $aggregation->of([$year, $bit], 'ifnull', $derivation)]);
    }

    public function testDoublesIsAsWideAsTheWidestValueBeforeMySql81(): void
    {
        $aggregation = new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')));
        $values = [Domain::double(3), Domain::integer(Field::Long, 11)];

        self::assertEquals(Domain::double(11), $aggregation->doubles($values, 'UNION', GrammarRelease::MySql8044));
        self::assertEquals(Domain::double(23), $aggregation->doubles($values, 'UNION', GrammarRelease::MySql847));
        self::assertEquals(Domain::double(23), $aggregation->doubles($values, 'UNION', GrammarRelease::MySql5744));
        self::assertEquals(Domain::double(11), $aggregation->doubles($values, 'if', GrammarRelease::MySql5744));
        self::assertEquals(Domain::double(22), $aggregation->of([Domain::double(3), Domain::double(22)], 'case', new Derivation((new Semantics(Dialect::MySql, 'mysql-8.0.44'))->context([]))));
    }

    public function testDoublesSettlesFloatsWithIntegersOnAFloat(): void
    {
        $aggregation = new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')));
        $float = new Domain(Kind::Double, Field::Float, 23, Domain::NOT_FIXED);

        self::assertSame([Field::Float, Field::Double, Field::Double], [$aggregation->doubles([$float, Domain::integer()], 'UNION', GrammarRelease::MySql847)->field, $aggregation->doubles([$float, Domain::decimal(2, 1)], 'UNION', GrammarRelease::MySql847)->field, $aggregation->doubles([$float, Domain::double()], 'UNION', GrammarRelease::MySql847)->field]);
    }

    public function testDoublesSettlesAFloatWithAnIntOrALeadingBigintOnADouble(): void
    {
        $aggregation = new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')));
        $float = new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED);

        self::assertSame([Field::Double, Field::Double, Field::Float], [$aggregation->doubles([$float, Domain::integer(Field::Long, 11)], 'ifnull', GrammarRelease::MySql847)->field, $aggregation->doubles([Domain::integer(Field::LongLong, 20), $float], 'ifnull', GrammarRelease::MySql847)->field, $aggregation->doubles([Domain::integer(Field::Int24, 9), $float], 'ifnull', GrammarRelease::MySql847)->field]);
    }
}
