<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlPartitionSelectionRewriter;
use ZtdQuery\Platform\MySql\MySqlSelectRelationParser;
use ZtdQuery\Schema\TablePartitioning;

#[CoversClass(MySqlPartitionSelectionRewriter::class)]
#[UsesClass(MySqlSelectRelationParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
final class MySqlPartitionSelectionRewriterTest extends TestCase
{
    public function testRewritesNamedPartitionAsFilteredDerivedTable(): void
    {
        $tables = [
            'EVENTS' => [
                'rows' => [],
                'columns' => ['id', 'event_date'],
                'columnTypes' => [],
                'partitioning' => new TablePartitioning([
                    'p2024' => 'YEAR(event_date) >= 2024 AND YEAR(event_date) < 2025',
                ]),
            ],
        ];

        self::assertSame(
            'SELECT events.id FROM (SELECT * FROM events WHERE (YEAR(event_date) >= 2024 AND YEAR(event_date) < 2025)) AS events',
            (new MySqlPartitionSelectionRewriter())->rewrite(
                'SELECT events.id FROM events PARTITION (p2024)',
                $tables,
            ),
        );
        self::assertSame(
            'SELECT EVENTS.id FROM (SELECT * FROM EVENTS WHERE (YEAR(event_date) >= 2024 AND YEAR(event_date) < 2025)) AS EVENTS',
            (new MySqlPartitionSelectionRewriter())->rewrite(
                'SELECT EVENTS.id FROM EVENTS PARTITION (p2024)',
                $tables,
            ),
        );
    }

    public function testPreservesAliasesAndRewritesEachJoinSource(): void
    {
        $tables = [
            'events' => [
                'rows' => [],
                'columns' => ['id'],
                'columnTypes' => [],
                'partitioning' => new TablePartitioning([
                    'p0' => 'id < 10',
                    'p1' => 'id >= 10',
                ]),
            ],
        ];

        self::assertSame(
            'SELECT a.id FROM (SELECT * FROM `events` WHERE (id < 10) OR (id >= 10)) AS a '
            . 'JOIN (SELECT * FROM events WHERE (id >= 10)) b ON b.id = a.id',
            (new MySqlPartitionSelectionRewriter())->rewrite(
                'SELECT a.id FROM app.`events` PARTITION (`p0`, p1) AS a '
                . 'JOIN events PARTITION (p1) b ON b.id = a.id',
                $tables,
            ),
        );
    }

    public function testLeavesOrdinarySelectUnchanged(): void
    {
        $sql = 'SELECT * FROM events';

        self::assertSame($sql, (new MySqlPartitionSelectionRewriter())->rewrite($sql, []));
    }

    public function testContinuesAfterOrdinarySourceAndAddsAliasBeforeJoinClause(): void
    {
        $tables = [
            'events' => [
                'rows' => [],
                'columns' => ['id'],
                'columnTypes' => [],
                'partitioning' => new TablePartitioning(['p0' => 'id < 10']),
            ],
        ];

        self::assertSame(
            'SELECT * FROM users JOIN (SELECT * FROM events WHERE (id < 10)) AS events ON events.id = users.id',
            (new MySqlPartitionSelectionRewriter())->rewrite(
                'SELECT * FROM users JOIN events PARTITION (p0) ON events.id = users.id',
                $tables,
            ),
        );
        self::assertSame(
            'SELECT * FROM (SELECT * FROM events WHERE (id < 10)) AS events join users ON users.id = events.id',
            (new MySqlPartitionSelectionRewriter())->rewrite(
                'SELECT * FROM events PARTITION (p0) join users ON users.id = events.id',
                $tables,
            ),
        );
    }

    public function testIgnoresClosingParenthesesBeforeThePartitionClause(): void
    {
        self::assertSame(
            'SELECT COALESCE(NULL, 0) FROM (SELECT * FROM events WHERE (id < 10)) AS events',
            (new MySqlPartitionSelectionRewriter())->rewrite(
                'SELECT COALESCE(NULL, 0) FROM events PARTITION (p0)',
                [
                    'events' => [
                        'rows' => [],
                        'columns' => ['id'],
                        'columnTypes' => [],
                        'partitioning' => new TablePartitioning(['p0' => 'id < 10']),
                    ],
                ],
            ),
        );
    }

    public function testRejectsUnknownPartitionName(): void
    {
        self::expectException(UnsupportedSqlException::class);

        (new MySqlPartitionSelectionRewriter())->rewrite(
            'SELECT * FROM events PARTITION (missing)',
            [
                'events' => [
                    'rows' => [],
                    'columns' => ['id'],
                    'columnTypes' => [],
                    'partitioning' => new TablePartitioning(['p0' => 'id < 10']),
                ],
            ],
        );
    }

    public function testRejectsSelectionWithoutPartitionMetadata(): void
    {
        self::expectException(UnsupportedSqlException::class);

        (new MySqlPartitionSelectionRewriter())->rewrite(
            'SELECT * FROM events PARTITION (p0)',
            ['events' => ['rows' => [], 'columns' => ['id'], 'columnTypes' => []]],
        );
    }

    public function testRejectsPartitionSelectionWithIndexHint(): void
    {
        self::expectException(UnsupportedSqlException::class);

        (new MySqlPartitionSelectionRewriter())->rewrite(
            'SELECT * FROM events PARTITION (p0) USE INDEX (PRIMARY)',
            [
                'events' => [
                    'rows' => [],
                    'columns' => ['id'],
                    'columnTypes' => [],
                    'partitioning' => new TablePartitioning(['p0' => 'id < 10']),
                ],
            ],
        );
    }

    public function testRejectsEmptyPartitionList(): void
    {
        self::expectException(UnsupportedSqlException::class);

        (new MySqlPartitionSelectionRewriter())->rewrite(
            'SELECT * FROM events PARTITION ()',
            [
                'events' => [
                    'rows' => [],
                    'columns' => ['id'],
                    'columnTypes' => [],
                    'partitioning' => new TablePartitioning(['p0' => 'id < 10']),
                ],
            ],
        );
    }

    public function testRejectsPartitionClauseWithoutOpeningParenthesis(): void
    {
        self::expectException(UnsupportedSqlException::class);
        self::expectExceptionMessage('PARTITION selection opening parenthesis');

        (new MySqlPartitionSelectionRewriter())->rewrite(
            'SELECT * FROM events PARTITION p0',
            ['events' => ['rows' => [], 'columns' => ['id'], 'columnTypes' => []]],
        );
    }

    public function testRejectsBracketsInPlaceOfPartitionParentheses(): void
    {
        self::expectException(UnsupportedSqlException::class);
        self::expectExceptionMessage('PARTITION selection opening parenthesis');

        (new MySqlPartitionSelectionRewriter())->rewrite(
            'SELECT * FROM events PARTITION [p0]',
            [
                'events' => [
                    'rows' => [],
                    'columns' => ['id'],
                    'columnTypes' => [],
                    'partitioning' => new TablePartitioning(['p0' => 'id < 10']),
                ],
            ],
        );
    }

    public function testRejectsPartitionClauseWithoutClosingParenthesis(): void
    {
        self::expectException(UnsupportedSqlException::class);
        self::expectExceptionMessage('PARTITION selection closing parenthesis');

        (new MySqlPartitionSelectionRewriter())->rewrite(
            'SELECT * FROM events PARTITION (p0',
            ['events' => ['rows' => [], 'columns' => ['id'], 'columnTypes' => []]],
        );
    }

    public function testRejectsMalformedPartitionNameList(): void
    {
        self::expectException(UnsupportedSqlException::class);

        (new MySqlPartitionSelectionRewriter())->rewrite(
            'SELECT * FROM events PARTITION (p0 extra)',
            [
                'events' => [
                    'rows' => [],
                    'columns' => ['id'],
                    'columnTypes' => [],
                    'partitioning' => new TablePartitioning(['p0' => 'id < 10']),
                ],
            ],
        );
    }

    public function testRejectsForceIndexHint(): void
    {
        self::expectException(UnsupportedSqlException::class);

        (new MySqlPartitionSelectionRewriter())->rewrite(
            'SELECT * FROM events PARTITION (p0) force INDEX (PRIMARY)',
            [
                'events' => [
                    'rows' => [],
                    'columns' => ['id'],
                    'columnTypes' => [],
                    'partitioning' => new TablePartitioning(['p0' => 'id < 10']),
                ],
            ],
        );
    }

    public function testRejectsIgnoreIndexHint(): void
    {
        self::expectException(UnsupportedSqlException::class);

        (new MySqlPartitionSelectionRewriter())->rewrite(
            'SELECT * FROM events PARTITION (p0) IGNORE INDEX (PRIMARY)',
            [
                'events' => [
                    'rows' => [],
                    'columns' => ['id'],
                    'columnTypes' => [],
                    'partitioning' => new TablePartitioning(['p0' => 'id < 10']),
                ],
            ],
        );
    }
}
