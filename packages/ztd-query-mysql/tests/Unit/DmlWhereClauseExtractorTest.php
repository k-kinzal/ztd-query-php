<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\DmlWhereClauseExtractor;

#[CoversClass(DmlWhereClauseExtractor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
final class DmlWhereClauseExtractorTest extends TestCase
{
    #[DataProvider('providerExtract')]
    public function testExtract(string $sql, ?string $expected): void
    {
        self::assertSame($expected, (new DmlWhereClauseExtractor())->extract($sql));
    }

    /** @return iterable<string, array{string, string|null}> */
    public static function providerExtract(): iterable
    {
        yield 'searched case' => [
            'UPDATE t SET score = 0 WHERE CASE WHEN score > 80 THEN 1 ELSE 0 END = 1',
            'CASE WHEN score > 80 THEN 1 ELSE 0 END = 1',
        ];
        yield 'nested subquery' => [
            'DELETE FROM t WHERE id IN (SELECT id FROM source WHERE active = 1) ORDER BY id LIMIT 2',
            'id IN (SELECT id FROM source WHERE active = 1)',
        ];
        yield 'quoted and commented keywords' => [
            "UPDATE t SET note = 'WHERE' /* WHERE */ WHERE id = 1 # ORDER BY\n LIMIT 1",
            'id = 1 # ORDER BY',
        ];
        yield 'without where' => [
            'DELETE FROM t ORDER BY id LIMIT 1',
            null,
        ];
    }
}
