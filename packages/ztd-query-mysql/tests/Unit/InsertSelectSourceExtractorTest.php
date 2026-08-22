<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\InsertSelectSourceExtractor;

#[CoversClass(InsertSelectSourceExtractor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
final class InsertSelectSourceExtractorTest extends TestCase
{
    #[DataProvider('providerSources')]
    public function testExtract(string $sql, ?string $expected): void
    {
        self::assertSame($expected, (new InsertSelectSourceExtractor())->extract($sql));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function providerSources(): iterable
    {
        yield 'union all' => [
            'INSERT INTO combined (name, amount) SELECT name, amount FROM archive UNION ALL SELECT name, amount FROM current',
            'SELECT name, amount FROM archive UNION ALL SELECT name, amount FROM current',
        ];
        yield 'nested select' => [
            'INSERT INTO combined SELECT * FROM (SELECT id FROM archive UNION SELECT id FROM current) AS source',
            'SELECT * FROM (SELECT id FROM archive UNION SELECT id FROM current) AS source',
        ];
        yield 'upsert suffix' => [
            'INSERT INTO combined SELECT id FROM source ON DUPLICATE KEY UPDATE id = VALUES(id)',
            'SELECT id FROM source',
        ];
        yield 'quoted keywords' => [
            "INSERT INTO combined SELECT 'ON DUPLICATE KEY UPDATE', 'RETURNING' FROM source",
            "SELECT 'ON DUPLICATE KEY UPDATE', 'RETURNING' FROM source",
        ];
        yield 'values insert' => ['INSERT INTO combined VALUES (1)', null];
        yield 'empty select' => ['INSERT INTO combined SELECT', null];
    }
}
