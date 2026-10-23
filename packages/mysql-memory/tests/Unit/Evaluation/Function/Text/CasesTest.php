<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Text;

use MySqlMemory\Evaluation\Function\Text\Cases;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Cases::class)]
#[Small]
final class CasesTest extends TestCase
{
    public function testRoutinesNamesTheCaseFunctions(): void
    {
        $names = array_map(static fn ($routine): string => $routine->name, (new Cases())->routines());

        self::assertSame(['UPPER', 'UCASE', 'LOWER', 'LCASE'], $names);
    }

    public function testUpperConvertsTheTextToUpperCase(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT UPPER('Hej'), UCASE('abc'), UPPER('é'), UPPER(_latin1 'abc'), UPPER(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['HEJ', 'ABC', 'É', 'ABC', null]], $result->rows);
    }

    public function testUpperLeavesABinaryStringUnchanged(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT UPPER(BINARY 'abc')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['abc']], $result->rows);
        self::assertTrue($result->columns[0]->binary());
    }

    public function testLowerConvertsTheTextToLowerCase(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LOWER('QUADRATICALLY'), LCASE('ABC'), LOWER('ÀBC'), LOWER(_latin1 'ABC'), LOWER(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['quadratically', 'abc', 'àbc', 'abc', null]], $result->rows);
    }

    public function testLowerLeavesABinaryStringUnchanged(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LOWER(BINARY 'ABC')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['ABC']], $result->rows);
    }

    public function testUpperMapsEachCharacterToOneCharacter(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT UPPER('straße'), LOWER('İ'), UPPER('ς'), LOWER('ΣΑΣ')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['STRAßE', 'i', 'Σ', 'σασ']], $result->rows);
    }

    public function testCasedFollowsTheUnicodeVersionOfTheCollation(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT HEX(UPPER(_utf8mb4'ა' COLLATE utf8mb4_0900_ai_ci)), HEX(UPPER(_utf8mb4'ꞵ' COLLATE utf8mb4_unicode_520_ci)), HEX(UPPER(_utf8mb4'ꞵ' COLLATE utf8mb4_0900_ai_ci)), HEX(UPPER(_utf8mb4'ϲ' COLLATE utf8mb4_general_ci)), HEX(UPPER(_utf8mb4'iı' COLLATE utf8mb4_turkish_ci)), HEX(LOWER(_utf8mb4'Iİ' COLLATE utf8mb4_turkish_ci)), HEX(UPPER(_utf8mb4'𐐨' COLLATE utf8mb4_general_ci)), HEX(UPPER(_utf8mb4'𐐨' COLLATE utf8mb4_0900_ai_ci))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['E18390', 'EA9EB5', 'EA9EB4', 'CEA3', 'C4B049', 'C4B169', 'F09090A8', 'F0909080']], $result->rows);
    }

    public function testCasedMapsUtf8InPlace(): void
    {
        $cases = new Cases();
        $collation = Collation::known('utf8mb4_0900_ai_ci');

        self::assertSame(["A\u{2C6F}", '', "\u{2C6F}", "a\u{2C66}"], [$cases->cased('aɐb', $collation, true), $cases->cased('ɐ', $collation, true), $cases->cased('ɐɐɐ', $collation, true), $cases->cased('AȾBȺC', $collation, false)]);
    }

    public function testCasedMapsTheCharactersOfASingleByteCharacterSet(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT HEX(UPPER(CONVERT('éÿšµ' USING latin1))), HEX(LOWER(CONVERT('ÉŠ' USING latin1))), HEX(UPPER(CONVERT('aé' USING ucs2)))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['C9FF9AB5', 'E98A', '004100C9']], $result->rows);
    }

    public function testMappedAppliesTheSimpleCaseMappingOfAVersion(): void
    {
        $cases = new Cases();

        self::assertSame([0x1C90, 0x10D0, 0x3A3, 0x130, 0xDF, 0x10400, 0x10428], [
            $cases->mapped(0x10D0, true, 99.0, false, false),
            $cases->mapped(0x10D0, true, 9.0, false, false),
            $cases->mapped(0x3F2, true, 3.0, true, false),
            $cases->mapped(0x69, true, 3.0, true, true),
            $cases->mapped(0xDF, true, 9.0, false, false),
            $cases->mapped(0x10428, true, 5.2, false, false),
            $cases->mapped(0x10428, true, 3.0, true, false),
        ]);
    }

    public function testInPlaceStopsAtACharacterThatWouldPassTheEnd(): void
    {
        self::assertSame(['XYZ', "\u{2C6F}", 'ABC'], [(new Cases())->inPlace('xyzɐ', true, 9.0), (new Cases())->inPlace('ɐıı', true, 9.0), (new Cases())->inPlace('abc', true, 3.0)]);
    }

    public function testPointAnswersTheCodePointOfACharacter(): void
    {
        self::assertSame([0xE9, null, null, 0xE9], [(new Cases())->point('é', 'UTF-8'), (new Cases())->point("\xC3", 'UTF-8'), (new Cases())->point('', 'UTF-8'), (new Cases())->point("\x00\xE9", 'UCS-2BE')]);
    }

    public function testThroughUnicodeKeepsACharacterWhoseMappingTheCharacterSetDoesNotHold(): void
    {
        $latin1 = Charset::known('latin1');

        self::assertSame(["\xC9\xFF", "\xB5"], [(new Cases())->throughUnicode("\xE9\xFF", $latin1, true, false), (new Cases())->throughUnicode("\xB5", $latin1, true, false)]);
    }

    public function testByCharacterMapsEachCharacterOfAUnicodeEncoding(): void
    {
        self::assertSame(["\x00A\x00\xC9", "\x010\x00I"], [(new Cases())->byCharacter("\x00a\x00\xE9", Charset::known('ucs2'), 'UCS-2BE', true, 3.0, false), (new Cases())->byCharacter("\x00i\x00I", Charset::known('ucs2'), 'UCS-2BE', true, 3.0, true)]);
    }
}
