<?php

declare(strict_types=1);

namespace Tests\Unit\System\Server;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Server\Keywords;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Keywords::class)]
#[Small]
final class KeywordsTest extends TestCase
{
    public function testRowsListsTheKeywordsOfTheRelease(): void
    {
        $s = (new Instance())->connect();

        $result1 = $s->query('SELECT * FROM information_schema.KEYWORDS LIMIT 2')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['ACCESSIBLE', '1'], ['ACCOUNT', '0']], $result1->rows);
        $result2 = $s->query('SELECT COUNT(*) FROM information_schema.KEYWORDS')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([['734']], $result2->rows);
    }
}
