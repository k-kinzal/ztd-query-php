<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Builtin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\FormatResults;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(FormatResults::class)]
#[Small]
final class FormatResultsTest extends TestCase
{
    public function testRulesResolveTheLengthsTheServerReports(): void
    {
        $rules = (new FormatResults())->rules();
        $settings = new Settings(Collation::known('utf8mb4_0900_ai_ci'));
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $text = Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible);

        self::assertSame(48, $rules['FORMAT'](new Invocation([Domain::decimal(10, 3), Domain::integer()], [], $settings, $derivation))?->length);
        self::assertSame(3, $rules['ELT'](new Invocation([Domain::integer(), $text, Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'))], [], $settings, $derivation))?->length);
        self::assertSame(5, $rules['MAKE_SET'](new Invocation([Domain::integer(), $text, Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'))], [], $settings, $derivation))?->length);
        self::assertSame(255, $rules['EXPORT_SET'](new Invocation([Domain::integer(), $text, $text], [], $settings, $derivation))?->length);
        self::assertSame(8, $rules['QUOTE'](new Invocation([$text], [], $settings, $derivation))?->length);
        self::assertSame(16, $rules['TO_BASE64'](new Invocation([$text], [], $settings, $derivation))?->length);
        self::assertSame(9, $rules['FROM_BASE64'](new Invocation([$text], [], $settings, $derivation))?->length);
        self::assertSame(65, $rules['CONV'](new Invocation([$text, Domain::integer(), Domain::integer()], [], $settings, $derivation))?->length);
        self::assertSame(24, $rules['HEX'](new Invocation([$text], [], $settings, $derivation))?->length);
        self::assertSame(6, $rules['UNHEX'](new Invocation([$text], [], $settings, $derivation))?->length);
        self::assertSame(30, $rules['COMPRESS'](new Invocation([$text], [], $settings, $derivation))?->length);
        self::assertSame(Field::LongBlob, $rules['UNCOMPRESS'](new Invocation([$text], [], $settings, $derivation))?->field);
        self::assertSame(21, $rules['ORD'](new Invocation([$text], [], $settings, $derivation))?->length);
    }

    public function testSizedChoosesTheTypeByTheBytes(): void
    {
        $sized = new FormatResults();
        $utf8 = Collation::known('utf8mb4_0900_ai_ci');

        self::assertSame([Field::VarString, 16383], [$sized->sized(16383, $utf8, Coercibility::Coercible)->field, $sized->sized(16383, $utf8, Coercibility::Coercible)->length]);
        self::assertSame([Field::MediumBlob, 65536], [$sized->sized(16384, $utf8, Coercibility::Coercible)->field, $sized->sized(16384, $utf8, Coercibility::Coercible)->length]);
        self::assertSame(Field::VarString, $sized->sized(65535, Collation::binary(), Coercibility::Coercible)->field);
        self::assertSame(Field::LongBlob, $sized->sized(16777216, Collation::binary(), Coercibility::Coercible)->field);
    }

    public function testSettledMakesAStringOfNumbersCoercible(): void
    {
        $domain = (new FormatResults())->settled(new Invocation([Domain::integer()], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))), [Domain::integer()], 'elt', static fn (Collation $collation): int => 2);

        self::assertSame(Coercibility::Coercible, $domain?->coercibility);
    }

    public function testWidthCountsTheBytesOfAStringMadeBinary(): void
    {
        $call = new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));
        $text = Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([4, 1], [(new FormatResults())->width($call, $text, Collation::binary()), (new FormatResults())->width($call, $text, Collation::known('latin1_swedish_ci'))]);
    }

    public function testBytesCountsNothingForNull(): void
    {
        self::assertSame([0, 12, 5], [(new FormatResults())->bytes(Domain::null()), (new FormatResults())->bytes(Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'))), (new FormatResults())->bytes(Domain::double(5))]);
    }

    public function testEncodedAddsANewlineEach76Characters(): void
    {
        self::assertSame([0, 4, 307, 316], [(new FormatResults())->encoded(0), (new FormatResults())->encoded(3), (new FormatResults())->encoded(228), (new FormatResults())->encoded(232)]);
    }

    public function testBoundAddsTheZlibBoundTheLengthAndAPeriod(): void
    {
        self::assertSame([18, 22, 80041], [(new FormatResults())->bound(0), (new FormatResults())->bound(4), (new FormatResults())->bound(80000)]);
    }

    public function testSoundexIsAtLeastFourCharactersAndBinaryForNull(): void
    {
        $call = new Invocation([Domain::null()], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame(['binary', 4, Coercibility::Ignorable], [(new FormatResults())->soundex($call)->collation->name, (new FormatResults())->soundex($call)->length, (new FormatResults())->soundex($call)->coercibility]);
    }

    public function testQuoteWritesANumberInLatin1(): void
    {
        $call = new Invocation([Domain::integer(Field::LongLong, 4)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame(['latin1_swedish_ci', 10, Coercibility::Numeric], [(new FormatResults())->quote($call)->collation->name, (new FormatResults())->quote($call)->length, (new FormatResults())->quote($call)->coercibility]);
    }

    public function testInsertedKeepsTheCollationOfTheFirstArgument(): void
    {
        $call = new Invocation([Domain::string(3, Collation::binary()), Domain::integer(), Domain::integer(), Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'))], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame(['binary', 7], [(new FormatResults())->inserted($call)->collation->name, (new FormatResults())->inserted($call)->length]);
    }

    public function testLegacyTellsMySql57FromMySql84(): void
    {
        self::assertSame([true, false], [(new FormatResults())->legacy(new Invocation([], [], new Settings(Collation::known('latin1_swedish_ci')), new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([])))), (new FormatResults())->legacy(new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))))]);
    }

    public function testUndecimalDropsTheDecimalsOfMySql57(): void
    {
        $domain = Domain::string(2, Collation::known('latin1_swedish_ci'));

        self::assertSame(0, (new FormatResults())->undecimal(new Invocation([], [], new Settings(Collation::known('latin1_swedish_ci')), new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]))), $domain)->decimals);
    }

    public function testDigitsOfCountsTwoForEachByteOrCharacter(): void
    {
        self::assertSame([24, 20, 0, 16], [(new FormatResults())->digitsOf(Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'))), (new FormatResults())->digitsOf(new Domain(Kind::Date, Field::NewDate, 10)), (new FormatResults())->digitsOf(Domain::null()), (new FormatResults())->digitsOf(Domain::integer())]);
    }
}
