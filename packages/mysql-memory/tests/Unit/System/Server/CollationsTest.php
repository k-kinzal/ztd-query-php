<?php

declare(strict_types=1);

namespace Tests\Unit\System\Server;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Reading;
use MySqlMemory\System\Server\Collations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(Collations::class)]
#[Small]
final class CollationsTest extends TestCase
{
    public function testRowsListsTheCollationsByCharacterSetAndId(): void
    {
        $s = (new Instance())->connect();

        $result1 = $s->query('SELECT * FROM information_schema.COLLATIONS')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['armscii8_general_ci', 'armscii8', '32', 'Yes', 'Yes', '1', 'PAD SPACE'], ['armscii8_bin', 'armscii8', '64', '', 'Yes', '1', 'PAD SPACE']], array_slice($result1->rows, 0, 2));
        $result2 = (new Instance('5.7.44'))->connect()->query('SELECT COLLATION_NAME FROM information_schema.COLLATIONS')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([['big5_chinese_ci'], ['big5_bin']], array_slice($result2->rows, 0, 2));
    }

    public function testOrderedAnswersTheCollationsInTheOrderOfTheRelease(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);

        self::assertSame(['armscii8_general_ci', 'armscii8_bin'], array_map(static fn ($collation): string => $collation->name, array_slice(Collations::ordered($reading), 0, 2)));
    }
}
