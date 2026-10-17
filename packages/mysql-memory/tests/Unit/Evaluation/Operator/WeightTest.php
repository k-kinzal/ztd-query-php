<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Weight;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\Globals;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;

#[CoversClass(Weight::class)]
#[Small]
final class WeightTest extends TestCase
{
    public function testPadHonorsTheExplicitResultCapacity(): void
    {
        $domain = Domain::string(2, Collation::binary());
        $weight = new Weight(new Constant($domain, 'ab'), null, null, $domain, resultLength: 4, flags: 128);

        self::assertSame("ab\0\0", $weight->pad('ab', $domain, 3, 2));
    }

    public function testBinaryReportsPacketOverflowEvenForNull(): void
    {
        $session = (new Instance(globals: ['max_allowed_packet' => 4096]))->connect();
        $result = $session->query('SELECT WEIGHT_STRING(NULL AS BINARY(8192))')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
        self::assertSame([['Warning', 1301, 'Result of cast_as_binary() was larger than max_allowed_packet (4096) - truncated']], $session->diagnostics->conditions);
    }

    public function testCountRetainsTheLegacyCharacterCapacityNarrowing(): void
    {
        $domain = Domain::string(3, Collation::known('utf8mb4_bin'));
        $weight = new Weight(new Constant($domain, 'abc'), WeightCast::Char, 2147483649, Domain::string(4, Collation::binary()));

        self::assertSame(1, $weight->count($domain, GrammarRelease::MySql5651));
        self::assertSame(2147483649, $weight->count($domain, GrammarRelease::MySql5744));
    }

    public function testEvaluateWarnsWhenBinaryWeightTruncatesTheArgument(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT HEX(WEIGHT_STRING('abc' AS BINARY(1)))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['61']], $result->rows);
        self::assertSame([['Warning', 1292, "Truncated incorrect BINARY(1) value: 'abc'"]], $session->diagnostics->conditions);
    }

    public function testDomainAnswersTheDomainOfTheWeight(): void
    {
        $domain = Domain::string(8, Collation::binary());

        self::assertSame($domain, (new Weight(new Constant(Domain::null(), null), null, null, $domain))->domain());
    }

    public function testEvaluateWeighsIntegersAndBinaryStrings(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT HEX(WEIGHT_STRING(1)), HEX(WEIGHT_STRING(-1 AS CHAR(1))), HEX(WEIGHT_STRING(CAST(18446744073709551615 AS UNSIGNED))), HEX(WEIGHT_STRING('a' AS BINARY(3))), HEX(WEIGHT_STRING(1 AS BINARY(2))), HEX(WEIGHT_STRING(1.5)), HEX(WEIGHT_STRING(1e0)), HEX(WEIGHT_STRING(NULL))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['8000000000000001', '7FFFFFFFFFFFFFFF', 'FFFFFFFFFFFFFFFF', '610000', '3100', null, null, null]], $result->rows);
    }

    public function testEvaluateWeighsAStringOfAUtf8BinaryCollationByCodePoint(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);

        self::assertSame("\0\0a\0\0B", (new Weight(new Constant(Domain::string(2, Collation::known('utf8mb4_bin')), 'aB'), null, null, Domain::string(24, Collation::binary())))->evaluate(new Frame($context)));
    }

    public function testTextPadsLegacyBinaryCharCastsToTheirRequestedLength(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $result = $session->query("SELECT HEX(WEIGHT_STRING(_binary'ab' AS CHAR(4)))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['61620000']], $result->rows);
    }

    public function testTextLimitsLegacyUnicodeExpansionsByWeightCount(): void
    {
        $collation = Collation::known('utf8mb4_unicode_520_ci');
        $domain = Domain::string(2, $collation);
        $operand = new Constant($domain, "\u{FDFA}a");
        $one = new Weight($operand, WeightCast::Char, 1, Domain::string(32, Collation::binary()));
        $four = new Weight($operand, null, null, Domain::string(32, Collation::binary()), 4);
        $padded = new Weight($operand, WeightCast::Char, 3, Domain::string(32, Collation::binary()));

        self::assertSame('18fc', bin2hex($one->text("\u{FDFA}a", $domain, GrammarRelease::MySql5744)));
        self::assertSame('18fc192b194f020a', bin2hex($four->text("\u{FDFA}a", $domain, GrammarRelease::MySql5651)));
        self::assertSame('120f020a020a', bin2hex($padded->text("a\u{301}", $domain, GrammarRelease::MySql5744)));
    }

    public function testEvaluateAppliesTheDocumentedLevelModifiers(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $result = $session->query("SELECT HEX(WEIGHT_STRING(_binary X'007FFF' LEVEL 1)), HEX(WEIGHT_STRING(_binary X'007FFF' LEVEL 1 DESC)), HEX(WEIGHT_STRING(_binary X'007FFF' LEVEL 1 REVERSE)), HEX(WEIGHT_STRING(_binary X'007FFF' LEVEL 1 DESC REVERSE))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['007FFF', 'FF8000', 'FF7F00', '0080FF']], $result->rows);
    }

    public function testEvaluateIgnoresModifiersForLevelsTheCollationDoesNotHave(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $result = $session->query("SELECT HEX(WEIGHT_STRING(_latin1'aB' LEVEL 2 DESC)), HEX(WEIGHT_STRING(_latin1'aB' LEVEL 2 REVERSE)), HEX(WEIGHT_STRING(_latin1'aB' LEVEL 1 REVERSE, 2 DESC))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['4142', '4142', '4241']], $result->rows);
    }

    public function testBytesPadsOrCutsToTheLengthOfTheCast(): void
    {
        $weight = new Weight(new Constant(Domain::null(), null), WeightCast::Binary, 3, Domain::string(8, Collation::binary()));

        self::assertSame(["ab\0", 'abc'], [$weight->bytes('ab'), $weight->bytes('abcd')]);
    }

    public function testEvaluateRefusesACollationWhoseWeightsAreNotEmulated(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1235);

        $session->query("SELECT HEX(WEIGHT_STRING(_utf8mb4'a' COLLATE utf8mb4_german2_ci))");
    }



}
