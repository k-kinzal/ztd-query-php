<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\UpdateSourceExtractor;

#[CoversClass(UpdateSourceExtractor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
final class UpdateSourceExtractorTest extends TestCase
{
    #[DataProvider('providerSources')]
    public function testExtract(string $sql, ?string $expected): void
    {
        self::assertSame($expected, (new UpdateSourceExtractor())->extract($sql));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function providerSources(): iterable
    {
        yield 'derived join' => [
            'UPDATE summary s JOIN (SELECT category, COUNT(*) AS cnt FROM products GROUP BY category) p ON s.category = p.category SET s.item_count = p.cnt',
            'summary s JOIN (SELECT category, COUNT(*) AS cnt FROM products GROUP BY category) p ON s.category = p.category',
        ];
        yield 'window expression and nested set token' => [
            "UPDATE rankings r JOIN (SELECT player, DENSE_RANK() OVER (ORDER BY MAX(score) DESC) AS ranking, 'SET' AS marker FROM scores GROUP BY player) s ON r.player = s.player SET r.rank_pos = s.ranking",
            "rankings r JOIN (SELECT player, DENSE_RANK() OVER (ORDER BY MAX(score) DESC) AS ranking, 'SET' AS marker FROM scores GROUP BY player) s ON r.player = s.player",
        ];
        yield 'modifiers and comments' => [
            'UPDATE /* before */ LOW_PRIORITY /* middle */ IGNORE /* source */ users AS u SET u.active = 1',
            'users AS u',
        ];
        yield 'quoted modifier name' => [
            'UPDATE `ignore` SET value = 1',
            '`ignore`',
        ];
        yield 'not update' => ['SELECT 1', null];
        yield 'missing source' => ['UPDATE SET value = 1', null];
    }
}
