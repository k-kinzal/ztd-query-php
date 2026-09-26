<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Sql\PlaceholderScanner;
use SqlCatalog\Core\Sql\SqlLexer;
use SqlCatalog\Core\Sql\SqlToken;
use SqlCatalog\Core\Sql\StatementKind;
use SqlCatalog\Core\Sql\StatementKindReader;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;

#[CoversClass(StatementKindReader::class)]
#[UsesClass(PlaceholderScanner::class)]
#[UsesClass(SqlLexer::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class StatementKindReaderTest extends TestCase
{
    #[DataProvider('providerRead')]
    public function testRead(string $sql, StatementKind $expected): void
    {
        self::assertSame($expected, (new StatementKindReader())->read(TextPattern::fromText($sql)));
    }

    /**
     * @return list<array{string, StatementKind}>
     */
    public static function providerRead(): array
    {
        return [
            ['SELECT 1', StatementKind::Select],
            ['  insert into t values (1)', StatementKind::Insert],
            ['/* lead */ UPDATE t SET a = 1', StatementKind::Update],
            ['-- lead' . "\n" . 'DELETE FROM t', StatementKind::Delete],
            ['REPLACE INTO t VALUES (1)', StatementKind::Replace],
            ['TRUNCATE TABLE t', StatementKind::Truncate],
            ['CREATE TABLE t (a INT)', StatementKind::Create],
            ['EXPLAIN SELECT 1', StatementKind::Explain],
            ['BEGIN', StatementKind::Transaction],
            ['SET NAMES utf8', StatementKind::Other],
            ['(SELECT 1) UNION (SELECT 2)', StatementKind::Select],
            ['WITH t AS (SELECT 1) SELECT * FROM t', StatementKind::Select],
            ['WITH t AS (SELECT 1) INSERT INTO u SELECT * FROM t', StatementKind::Insert],
            ['WITH t AS (SELECT 1)', StatementKind::Unknown],
            ['', StatementKind::Unknown],
            ['1 + 1', StatementKind::Unknown],
            ['FLUSH PRIVILEGES', StatementKind::Unknown],
        ];
    }

    public function testReadIsUnknownWhenAGapHidesTheLeadingKeyword(): void
    {
        $pattern = TextPattern::fromHole(new TextHole(Origin::External, TypeShape::unknown()))
            ->concat(TextPattern::fromText(' FROM users'));
        self::assertSame(StatementKind::Unknown, (new StatementKindReader())->read($pattern));
    }

    public function testReadAfterCommonTableIgnoresNestedKeywords(): void
    {
        $tokens = (new SqlLexer())->tokenize('a AS (SELECT 1) DELETE FROM t');
        self::assertSame(StatementKind::Delete, (new StatementKindReader())->readAfterCommonTable($tokens));
    }
}
