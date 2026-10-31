<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Builtin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\DigestResults;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DigestResults::class)]
#[Small]
final class DigestResultsTest extends TestCase
{
    public function testRulesTypeDigestsInTheConnectionCollation(): void
    {
        $rules = (new DigestResults())->rules();
        $call = new Invocation([Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'))], [], new Settings(Collation::known('latin1_swedish_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertEquals(Domain::string(32, Collation::known('latin1_swedish_ci'), Field::VarString, Coercibility::Coercible), $rules['MD5']($call));
        self::assertEquals(Domain::string(36, Collation::known('utf8mb3_general_ci'), Field::VarString, Coercibility::Coercible), $rules['UUID']($call));
        self::assertEquals(Domain::string(1024, Collation::binary()), $rules['RANDOM_BYTES']($call));
        self::assertEquals(Domain::integer(Field::LongLong, 21, true), $rules['INET_ATON']($call));
    }

    public function testRulesTypeTheAddressesOf57WithoutDecimals(): void
    {
        $rules = (new DigestResults())->rules();
        $call = new Invocation([], [], new Settings(Collation::known('latin1_swedish_ci')), new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([])));

        self::assertSame([31, 0, 0], [$rules['INET_NTOA']($call)?->length, $rules['INET_NTOA']($call)?->decimals, $rules['INET6_ATON']($call)?->decimals]);
    }

    public function testRulesPadAesInBlockModesOnly(): void
    {
        $rules = (new DigestResults())->rules();
        $text = Domain::string(4, Collation::known('utf8mb4_0900_ai_ci'));
        $block = new Invocation([$text], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));
        $stream = new Invocation([$text], [], new Settings(Collation::known('utf8mb4_0900_ai_ci'), blockEncryptionMode: 'aes-128-cfb8'), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame([32, 16, 4], [$rules['AES_ENCRYPT']($block)?->length, $rules['AES_ENCRYPT']($stream)?->length, $rules['AES_DECRYPT']($block)?->length]);
    }

    public function testSha2IsAsLongAsTheDigestALiteralNames(): void
    {
        $results = new DigestResults();
        $context = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $settings = new Settings(Collation::known('utf8mb4_0900_ai_ci'));
        $text = Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([56, 64, 128], [$results->sha2(new Invocation([$text, Domain::integer()], [new StringLiteral(['a']), new NumberLiteral('224')], $settings, $context))->length, $results->sha2(new Invocation([$text, Domain::null()], [new StringLiteral(['a']), new NullLiteral()], $settings, $context))->length, $results->sha2(new Invocation([$text, $text], [new StringLiteral(['a']), new FunctionCall(new Name('f'))], $settings, $context))->length]);
    }

    public function testSha2IsAnEmptyBinaryStringForALengthItDoesNotTakeIn56(): void
    {
        $call = new Invocation([Domain::string(1, Collation::known('latin1_swedish_ci')), Domain::integer()], [new StringLiteral(['a']), new NumberLiteral('1')], new Settings(Collation::known('latin1_swedish_ci')), new Derivation((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context([])));

        self::assertEquals(Domain::string(0, Collation::binary()), (new DigestResults())->sha2($call));
    }

    public function testLiteralReadsTheIntegerALiteralWrites(): void
    {
        self::assertSame([257, null, -224, 224, false], [DigestResults::literal(new NumberLiteral('256.7')), DigestResults::literal(new NullLiteral()), DigestResults::literal(new Unary(UnaryOperator::Minus, new NumberLiteral('224'))), DigestResults::literal(new StringLiteral([' 224x'])), DigestResults::literal(null)]);
    }

    public function testLegacyTellsTheReleasesBefore80(): void
    {
        $settings = new Settings(Collation::known('latin1_swedish_ci'));

        self::assertSame([true, false], [DigestResults::legacy(new Invocation([], [], $settings, new Derivation((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context([])))), DigestResults::legacy(new Invocation([], [], $settings, new Derivation((new Semantics(Dialect::MySql))->context([]))))]);
    }

    public function testPlainHasNoDecimals(): void
    {
        $call = new Invocation([], [], new Settings(Collation::known('latin1_swedish_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame([39, 0], [DigestResults::plain($call, 39)->length, DigestResults::plain($call, 39)->decimals]);
    }

    public function testTextIsACoercibleStringOfTheConnection(): void
    {
        $call = new Invocation([], [], new Settings(Collation::known('latin1_swedish_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame([Coercibility::Coercible, 'latin1_swedish_ci'], [DigestResults::text($call, 4)->coercibility, DigestResults::text($call, 4)->collation->name]);
    }

    public function testStreamTellsTheStreamModes(): void
    {
        $context = new Derivation((new Semantics(Dialect::MySql))->context([]));

        self::assertSame([true, false], [DigestResults::stream(new Invocation([], [], new Settings(Collation::known('latin1_swedish_ci'), blockEncryptionMode: 'aes-256-ofb'), $context)), DigestResults::stream(new Invocation([], [], new Settings(Collation::known('latin1_swedish_ci'), blockEncryptionMode: 'aes-256-cbc'), $context))]);
    }

    public function testBytesCountsTheBytesOfAnArgument(): void
    {
        $call = new Invocation([Domain::string(4, Collation::known('utf8mb4_0900_ai_ci')), Domain::integer(Field::LongLong, 5)], [], new Settings(Collation::known('latin1_swedish_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame([16, 5], [DigestResults::bytes($call, 0), DigestResults::bytes($call, 1)]);
    }

    public function testPasswordIsAsLongAsTheHashOfALiteral(): void
    {
        $context = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]));
        $settings = new Settings(Collation::known('latin1_swedish_ci'));

        self::assertSame([41, 0, 79], [DigestResults::password(new Invocation([], [new StringLiteral(['a'])], $settings, $context)), DigestResults::password(new Invocation([], [new StringLiteral([''])], $settings, $context)), DigestResults::password(new Invocation([], [new NullLiteral()], $settings, $context))]);
    }
}
