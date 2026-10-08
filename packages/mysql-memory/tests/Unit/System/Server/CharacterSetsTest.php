<?php

declare(strict_types=1);

namespace Tests\Unit\System\Server;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Server\CharacterSets;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(CharacterSets::class)]
#[Small]
final class CharacterSetsTest extends TestCase
{
    public function testRowsListsTheCharacterSetsByTheIdOfTheirDefaultCollation(): void
    {
        $s = (new Instance())->connect();

        $result1 = $s->query('SELECT * FROM information_schema.CHARACTER_SETS')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['big5', 'big5_chinese_ci', 'Big5 Traditional Chinese', '2'], ['dec8', 'dec8_swedish_ci', 'DEC West European', '1']], array_slice($result1->rows, 0, 2));
        $result2 = (new Instance('5.7.44'))->connect()->query("SELECT CHARACTER_SET_NAME, DEFAULT_COLLATE_NAME FROM information_schema.CHARACTER_SETS WHERE CHARACTER_SET_NAME = 'utf8'")[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([['utf8', 'utf8_general_ci']], $result2->rows);
    }
}
