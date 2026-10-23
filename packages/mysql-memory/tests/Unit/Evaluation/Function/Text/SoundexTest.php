<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Text;

use MySqlMemory\Evaluation\Function\Text\Soundex;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

#[CoversClass(Soundex::class)]
#[Small]
final class SoundexTest extends TestCase
{
    public function testRoutinesNamesSoundex(): void
    {
        self::assertSame(['SOUNDEX'], array_map(static fn ($routine): string => $routine->name, (new Soundex())->routines()));
    }

    public function testSoundexAnswersTheCodeInTheCharacterSetOfTheArgument(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT SOUNDEX('Quadratically'), SOUNDEX('Tymczak'), SOUNDEX('ÄÖÜ'), SOUNDEX('ab日c'), SOUNDEX('ªb'), SOUNDEX(_binary'Ébc'), SOUNDEX(CONVERT('ÿb' USING latin1)), SOUNDEX(''), SOUNDEX(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['Q36324', 'T520', 'Ä000', 'A120', 'B000', 'B200', 'ÿ100', '', null]], $result->rows);
        self::assertSame(52, $result->columns[0]->length);
    }

    public function testCodeKeepsTheFirstLetterAndSkipsRepeatedDigits(): void
    {
        self::assertSame(['A2613', 'B000', ''], [(new Soundex())->code('Ashcraft', Charset::known('utf8mb4')), (new Soundex())->code('Bwb', Charset::known('utf8mb4')), (new Soundex())->code('123', Charset::known('utf8mb4'))]);
    }

    public function testLetterFollowsTheCharacterSet(): void
    {
        self::assertSame([true, false, true, false, false], [(new Soundex())->letter('é', 'é', Charset::known('utf8mb4')), (new Soundex())->letter("\u{AA}", "\u{AA}", Charset::known('utf8mb4')), (new Soundex())->letter("\x8A", 'Š', Charset::known('latin1')), (new Soundex())->letter("\xD7", '×', Charset::known('latin1')), (new Soundex())->letter("\xC3", "\xC3", Charset::binary())]);
    }
}
