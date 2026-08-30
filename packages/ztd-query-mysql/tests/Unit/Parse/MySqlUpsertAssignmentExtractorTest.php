<?php

declare(strict_types=1);

namespace Tests\Unit\Parse;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Dialect\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\Parse\MySqlUpsertAssignmentExtractor;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(MySqlUpsertAssignmentExtractor::class)]
#[UsesClass(MySqlLexerProfile::class)]
final class MySqlUpsertAssignmentExtractorTest extends TestCase
{
    /**
     * @param array<string, string> $expected
     */
    #[DataProvider('providerAssignments')]
    public function testExtract(string $sql, array $expected): void
    {
        self::assertSame($expected, (new MySqlUpsertAssignmentExtractor())->extract($sql));
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function providerAssignments(): iterable
    {
        yield 'row alias' => [
            "INSERT INTO t VALUES (1, 'updated', 99) AS new ON DUPLICATE KEY UPDATE name = new.name, score = new.score",
            ['name' => 'new.name', 'score' => 'new.score'],
        ];
        yield 'quoted row alias and columns' => [
            "INSERT INTO t VALUES (1, 'updated') AS `incoming` ON DUPLICATE KEY UPDATE `t`.`name` = `incoming`.`name`",
            ['name' => '`incoming`.`name`'],
        ];
        yield 'case-insensitive row alias' => [
            "INSERT INTO t VALUES (1, 'updated') AS Incoming ON DUPLICATE KEY UPDATE name = incoming.name",
            ['name' => 'incoming.name'],
        ];
        yield 'values function and nested commas' => [
            'INSERT INTO t VALUES (1, 2) ON DUPLICATE KEY UPDATE value = IF(VALUES(value) > value, VALUES(value), value)',
            ['value' => 'IF(VALUES(value) > value, VALUES(value), value)'],
        ];
        yield 'multiple alias references after other identifiers' => [
            'INSERT INTO t VALUES (1, 2) AS incoming ON DUPLICATE KEY UPDATE value = value + incoming.value + incoming.value',
            ['value' => 'value + incoming.value + incoming.value'],
        ];
        yield 'alias word without qualification' => [
            'INSERT INTO t VALUES (1, 2) AS incoming ON DUPLICATE KEY UPDATE value = incoming + incoming.value',
            ['value' => 'incoming + incoming.value'],
        ];
        yield 'select alias is not a row alias' => [
            'INSERT INTO t SELECT other.value AS projected FROM other ON DUPLICATE KEY UPDATE value = projected.value',
            ['value' => 'projected.value'],
        ];
        yield 'quoted keywords' => [
            "INSERT INTO t VALUES (1, 'ON DUPLICATE KEY UPDATE') ON DUPLICATE KEY UPDATE value = 'RETURNING'",
            ['value' => "'RETURNING'"],
        ];
        yield 'not upsert' => ['INSERT INTO t VALUES (1)', []];
        yield 'missing on keyword' => [
            'INSERT INTO t VALUES (1) DUPLICATE KEY UPDATE value = 2',
            [],
        ];
        yield 'malformed assignments' => [
            'INSERT INTO t VALUES (1) ON DUPLICATE KEY UPDATE missing, value =',
            [],
        ];
    }

    public function testIncomingAliasExtractsIncomingRowAliasWithoutRewritingExpressions(): void
    {
        $extractor = new MySqlUpsertAssignmentExtractor();

        self::assertSame(
            'incoming',
            $extractor->incomingAlias(
                'INSERT INTO t VALUES (1) AS `incoming` ON DUPLICATE KEY UPDATE value = incoming.value',
            ),
        );
        self::assertSame(
            'incoming',
            $extractor->incomingAlias(
                'WITH payload AS (SELECT 1) INSERT INTO t VALUES (1) AS incoming '
                . 'ON DUPLICATE KEY UPDATE value = incoming.value',
            ),
        );
        self::assertNull($extractor->incomingAlias('INSERT INTO t VALUES (1) ON DUPLICATE KEY UPDATE value = 1'));
    }
    public function testAssignmentReadsTheColumnAndWhatIsAssignedToIt(): void
    {
        self::assertSame(
            ['column' => 'qty', 'value' => '1'],
            (new MySqlUpsertAssignmentExtractor())->assignment('qty = 1'),
        );
    }

    public function testAssignmentIsNothingWhereTheTextAssignsNothing(): void
    {
        self::assertNull((new MySqlUpsertAssignmentExtractor())->assignment('qty'));
    }

    public function testLastIdentifierAnswersTheNameWrittenLast(): void
    {
        $tokens = SqlTokenStream::tokenize('t.qty', MySqlLexerProfile::create())->significantTokens();

        self::assertSame('qty', (new MySqlUpsertAssignmentExtractor())->lastIdentifier($tokens));
    }

    public function testLastIdentifierIsNothingWhereTheLastTokenIsNotAName(): void
    {
        $tokens = SqlTokenStream::tokenize('qty =', MySqlLexerProfile::create())->significantTokens();

        self::assertNull((new MySqlUpsertAssignmentExtractor())->lastIdentifier($tokens));
    }

}
