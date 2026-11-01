<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseWord;

#[CoversClass(DatabaseWord::class)]
#[Medium]
final class DatabaseWordTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, string}>
     */
    public static function providerQueries(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (DatabaseWord::cases() as $word) {
                foreach ([
                    "CREATE $word->value d",
                    "CREATE $word->value IF NOT EXISTS d CHARACTER SET utf8mb4",
                    "ALTER $word->value d COLLATE utf8mb4_bin",
                    "ALTER $word->value CHARACTER SET latin1",
                    "DROP $word->value IF EXISTS d",
                    "SHOW CREATE $word->value d",
                    "SHOW CREATE $word->value IF NOT EXISTS d",
                ] as $sql) {
                    yield "$release->value/$sql" => [$release, $sql];
                }
                if (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                    yield "$release->value/upgrade/$word->value" => [$release, "ALTER $word->value `#mysql50#d` UPGRADE DATA DIRECTORY NAME"];
                } else {
                    yield "$release->value/read-only/$word->value" => [$release, "ALTER $word->value READ ONLY DEFAULT"];
                }
            }
        }
    }

    #[DataProvider('providerQueries')]
    public function testRenderRetainsTheDatabaseKeywordInEachStatementRole(GrammarRelease $release, string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql, $release->value);
        $formatter = new \SqlFormatter\Facade\Formatter($semantics->parser(), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        self::assertSame($formatter->format($sql), $formatter->format((new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context(), $semantics->analyze($sql))->toString()));
    }
}
