<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\UpdateAssignmentExtractor;

#[CoversClass(UpdateAssignmentExtractor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
final class UpdateAssignmentExtractorTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerValues')]
    public function testValues(string $sql, array $expected): void
    {
        self::assertSame($expected, (new UpdateAssignmentExtractor())->values($sql));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerValues(): iterable
    {
        yield 'hex literal' => [
            "UPDATE data SET payload = X'576F726C64' WHERE id = 1",
            ["X'576F726C64'"],
        ];
        yield 'binary and national literals' => [
            "UPDATE data SET bits = B'0101', label = N'text'",
            ["B'0101'", "N'text'"],
        ];
        yield 'qualified target column' => [
            "UPDATE data d SET d.payload = X'00'",
            ["X'00'"],
        ];
        yield 'nested commas and equality' => [
            "UPDATE data SET value = IF(flag = 1, CONCAT('a', 'b'), 'c'), rank = 2 ORDER BY id LIMIT 1",
            ["IF(flag = 1, CONCAT('a', 'b'), 'c')", '2'],
        ];
        yield 'interval arithmetic' => [
            'UPDATE tasks SET due_at = created_at + INTERVAL 30 DAY',
            ['created_at + INTERVAL 30 DAY'],
        ];
        yield 'comments' => [
            "UPDATE data SET payload /* equals */ = X'00' # value\n WHERE id = 1",
            ["X'00' # value"],
        ];
        yield 'not update' => [
            'SELECT 1',
            [],
        ];
    }
}
