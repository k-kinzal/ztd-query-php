<?php

declare(strict_types=1);

namespace Tests\Unit;

use PhpMyAdmin\SqlParser\Parser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Contract\QueryClassifierContractTest;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;
use ZtdQuery\Rewrite\QueryKind;

#[CoversClass(MySqlQueryGuard::class)]
#[UsesClass(MySqlParser::class)]
class MySqlQueryGuardTest extends QueryClassifierContractTest
{
    protected function classify(string $sql): ?QueryKind
    {
        return (new MySqlQueryGuard(new MySqlParser()))->classify($sql);
    }

    protected function selectSql(): string
    {
        return 'SELECT id, name FROM users WHERE id = 1';
    }

    protected function insertSql(): string
    {
        return "INSERT INTO users (id, name) VALUES (1, 'Alice')";
    }

    protected function updateSql(): string
    {
        return "UPDATE users SET name = 'Bob' WHERE id = 1";
    }

    protected function deleteSql(): string
    {
        return 'DELETE FROM users WHERE id = 1';
    }

    protected function createTableSql(): string
    {
        return 'CREATE TABLE orders (id INT PRIMARY KEY, amount DECIMAL(10,2))';
    }

    protected function dropTableSql(): string
    {
        return 'DROP TABLE orders';
    }

    public function testClassifiesReadStatements(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::READ, $guard->classify('SELECT * FROM users'));
        self::assertSame(QueryKind::READ, $guard->classify('WITH cte AS (SELECT 1) SELECT * FROM users'));
    }

    public function testClassifiesWriteStatements(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::WRITE_SIMULATED, $guard->classify('UPDATE users SET name = "A" WHERE id = 1'));
        self::assertSame(QueryKind::WRITE_SIMULATED, $guard->classify('DELETE FROM users WHERE id = 1'));
        self::assertSame(QueryKind::WRITE_SIMULATED, $guard->classify('INSERT INTO users (id, name) VALUES (1, "A")'));
        self::assertSame(QueryKind::WRITE_SIMULATED, $guard->classify('WITH cte AS (SELECT 1) UPDATE users SET name = "B" WHERE id = 1'));
        self::assertSame(QueryKind::WRITE_SIMULATED, $guard->classify('WITH `tmp31862` AS( SELECT x862873 ) UPDATE tmp743270 SET tmp526670 = y32462'));
    }

    public function testReturnsNullForUnsupportedStatements(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify('DROP DATABASE test'));
        self::assertNull($guard->classify('CREATE DATABASE test'));
        self::assertNull($guard->classify('SELECT 1; SELECT 2'));
    }

    public function testClassifiesDdlStatements(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::DDL_SIMULATED, $guard->classify('DROP TABLE users'));
        self::assertSame(QueryKind::DDL_SIMULATED, $guard->classify('CREATE TABLE demo (id INT)'));
        self::assertSame(QueryKind::DDL_SIMULATED, $guard->classify('ALTER TABLE users ADD COLUMN email VARCHAR(255)'));
        self::assertSame(QueryKind::DDL_SIMULATED, $guard->classify('ALTER TABLE users DROP COLUMN email'));
        self::assertSame(QueryKind::DDL_SIMULATED, $guard->classify('ALTER TABLE users MODIFY COLUMN name VARCHAR(500)'));
        self::assertSame(QueryKind::DDL_SIMULATED, $guard->classify('ALTER TABLE users CHANGE COLUMN name full_name VARCHAR(255)'));
        self::assertSame(QueryKind::DDL_SIMULATED, $guard->classify('ALTER TABLE users RENAME TO members'));
    }

    public function testAssertAllowedThrowsOnForbidden(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('ZTD Write Protection');

        $guard->assertAllowed('DROP DATABASE test');
    }

    public function testAssertAllowedAcceptsParsedStatement(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $parser = new Parser('SELECT 1');
        $statement = $parser->statements[0];

        $guard->assertAllowed($statement);

        self::assertSame(QueryKind::READ, $guard->classify('SELECT 1'));
    }

    public function testClassifiesSelectIntoAsNull(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify('SELECT * INTO OUTFILE "/tmp/data" FROM users'));
    }

    public function testClassifiesTruncateAsWriteSimulated(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::WRITE_SIMULATED, $guard->classify('TRUNCATE TABLE users'));
    }

    public function testClassifiesReplaceAsWriteSimulated(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::WRITE_SIMULATED, $guard->classify("REPLACE INTO users (id, name) VALUES (1, 'Alice')"));
    }

    public function testClassifiesEmptySqlAsNull(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify(''));
    }

    public function testClassifiesCreateIndexAsNull(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify('CREATE INDEX idx_name ON users (name)'));
    }

    public function testClassifiesDropIndexAsNull(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify('DROP INDEX idx_name ON users'));
    }

    public function testAssertAllowedDoesNotThrowForWrite(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $guard->assertAllowed("INSERT INTO users (id) VALUES (1)");
        self::assertSame(QueryKind::WRITE_SIMULATED, $guard->classify("INSERT INTO users (id) VALUES (1)"));
    }

    public function testAssertAllowedDoesNotThrowForDdl(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $guard->assertAllowed('CREATE TABLE t (id INT)');
        self::assertSame(QueryKind::DDL_SIMULATED, $guard->classify('CREATE TABLE t (id INT)'));
    }

    public function testClassifyWithStatementNoCteParser(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::READ, $guard->classify('WITH cte AS (SELECT 1) SELECT * FROM cte'));
    }

    public function testClassifyWithInsertFallback(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::WRITE_SIMULATED, $guard->classify('WITH cte AS (SELECT 1) INSERT INTO users SELECT * FROM cte'));
    }

    public function testClassifyWithDeleteFallback(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::WRITE_SIMULATED, $guard->classify('WITH cte AS (SELECT 1) DELETE FROM users WHERE id IN (SELECT * FROM cte)'));
    }

    public function testClassifyWithSelectFallback(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::READ, $guard->classify('WITH cte AS (SELECT 1) SELECT * FROM cte'));
    }

    public function testClassifyWithQuotedStringsFallback(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $result = $guard->classify("WITH cte AS (SELECT 'SELECT') SELECT * FROM cte");
        self::assertSame(QueryKind::READ, $result);
    }

    public function testClassifyMultipleStatementsReturnsNull(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify('SELECT 1; SELECT 2'));
    }

    public function testClassifyAlterWithoutTableReturnsNull(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify('ALTER DATABASE test DEFAULT CHARACTER SET utf8'));
    }

    public function testAssertAllowedThrowsForSelectInto(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::expectException(\RuntimeException::class);
        $guard->assertAllowed('SELECT * INTO OUTFILE "/tmp/data" FROM users');
    }

    public function testClassifyWithEscapedBackslashInStringFallback(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $result = $guard->classify("WITH cte AS (SELECT 'it\\'s') SELECT * FROM cte");
        self::assertSame(QueryKind::READ, $result);
    }

    public function testClassifyWithDoubleQuotedStringFallback(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $result = $guard->classify('WITH cte AS (SELECT "DELETE") SELECT * FROM cte');
        self::assertSame(QueryKind::READ, $result);
    }

    public function testClassifyWithBacktickQuotedIdentifierFallback(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $result = $guard->classify('WITH cte AS (SELECT `UPDATE` FROM t) SELECT * FROM cte');
        self::assertSame(QueryKind::READ, $result);
    }

    public function testClassifyWithNestedParensFallback(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $result = $guard->classify('WITH cte AS (SELECT (1+2)) SELECT * FROM cte');
        self::assertSame(QueryKind::READ, $result);
    }

    public function testClassifyWithFallbackNonAlphaAfterKeyword(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $result = $guard->classify('WITH cte AS (SELECT 1) DELETE FROM users WHERE id = 1');
        self::assertSame(QueryKind::WRITE_SIMULATED, $result);
    }

    public function testClassifyWithNullCteParserReturnsRead(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $result = $guard->classify('WITH cte AS (SELECT 1) GRANT ALL ON users TO admin');
        self::assertSame(QueryKind::READ, $result);
    }

    public function testClassifyDropViewReturnsNull(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify('DROP VIEW v'));
    }

    public function testClassifyCreateViewReturnsNull(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify('CREATE VIEW v AS SELECT 1'));
    }

    public function testClassifyStatementWithCreateNonTableReturnsNull(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify('CREATE INDEX idx ON t(c)'));
    }

    public function testClassifyStatementWithAlterNonTableReturnsNull(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify('ALTER DATABASE test CHARACTER SET utf8'));
    }

    public function testClassifyStatementDropNonTableReturnsNull(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify('DROP INDEX idx ON t'));
    }

    public function testClassifyWithFallbackDeleteInWith(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::WRITE_SIMULATED, $guard->classify('WITH cte AS (SELECT 1) DELETE FROM users WHERE id = 1'));
    }

    public function testClassifyWithFallbackInsertInWith(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::WRITE_SIMULATED, $guard->classify("WITH cte AS (SELECT 1) INSERT INTO users (id) VALUES (1)"));
    }

    public function testClassifyWithFallbackReplaceInWithReturnsNull(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify("WITH cte AS (SELECT 1) REPLACE INTO users (id) VALUES (1)"));
    }

    public function testClassifyWithFallbackTruncateInWithReturnsNull(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify('WITH cte AS (SELECT 1) TRUNCATE TABLE users'));
    }

    public function testClassifyWithFallbackCreateInWithReturnsNull(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify('WITH cte AS (SELECT 1) CREATE TABLE t (id INT)'));
    }

    public function testClassifyWithFallbackDropInWithReturnsNull(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify('WITH cte AS (SELECT 1) DROP TABLE users'));
    }

    public function testClassifyWithFallbackAlterInWithReturnsNull(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify('WITH cte AS (SELECT 1) ALTER TABLE users ADD COLUMN x INT'));
    }

    public function testClassifyWithFallbackQuotedStringContainingKeyword(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::READ, $guard->classify("WITH cte AS (SELECT 'DELETE FROM x') SELECT * FROM users"));
    }

    public function testClassifyWithFallbackBacktickQuotedIdentifier(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::READ, $guard->classify("WITH `DELETE` AS (SELECT 1) SELECT * FROM `DELETE`"));
    }

    public function testClassifyWithFallbackDoubleQuotedString(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::WRITE_SIMULATED, $guard->classify('WITH cte AS (SELECT "test") UPDATE t SET a = 1'));
    }

    public function testClassifyWithFallbackEscapedQuoteInString(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::WRITE_SIMULATED, $guard->classify("WITH cte AS (SELECT 'it\\'s') UPDATE t SET a = 1"));
    }

    public function testClassifyWithFallbackLowercaseKeywords(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::WRITE_SIMULATED, $guard->classify('with cte as (select 1) update t set a = 1'));
    }

    public function testClassifyStatementSelectIntoReturnsNull(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertNull($guard->classify('SELECT * INTO OUTFILE "/tmp/data" FROM users'));
    }

    public function testClassifyCreateNonTableDoesNotFallThroughToAlter(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $result = $guard->classify('CREATE INDEX idx ON t(c)');
        self::assertNull($result);
    }

    public function testClassifyDropNonTableDoesNotFallThroughToAlter(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $result = $guard->classify('DROP INDEX idx ON t');
        self::assertNull($result);
    }

    public function testClassifyAlterNonTableDoesNotFallThroughToWith(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $result = $guard->classify('ALTER DATABASE test CHARACTER SET utf8');
        self::assertNull($result);
    }

    public function testClassifyTruncateIsWriteSimulated(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::WRITE_SIMULATED, $guard->classify('TRUNCATE TABLE users'));
    }

    public function testClassifyReplaceIsWriteSimulated(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(QueryKind::WRITE_SIMULATED, $guard->classify("REPLACE INTO users (id, name) VALUES (1, 'Alice')"));
    }

    public function testAssertAllowedThrowsForUnsupportedSql(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::expectException(\RuntimeException::class);
        $guard->assertAllowed('DROP DATABASE test');
    }

    public function testAssertAllowedDoesNotThrowForSelect(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $guard->assertAllowed('SELECT 1');
        self::assertSame(QueryKind::READ, $guard->classify('SELECT 1'));
    }

    public function testAssertAllowedWithParsedStatement(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $parser = new Parser('SELECT * FROM users');
        $guard->assertAllowed($parser->statements[0]);
        self::assertSame(QueryKind::READ, $guard->classify('SELECT * FROM users'));
    }

    public function testClassifyStatementReturnsReadForParsedSelectDirectly(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $mySqlParser = new MySqlParser();
        $stmts = $mySqlParser->parse('SELECT 1');
        self::assertSame(QueryKind::READ, $guard->classifyStatement($stmts[0]));
    }

    public function testClassifyStatementReturnsNullForCreateNonTableDirectly(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $parser = new Parser('CREATE INDEX idx ON t(c)');
        $result = $guard->classifyStatement($parser->statements[0]);
        self::assertNull($result);
    }

    public function testClassifyStatementReturnsNullForDropNonTableDirectly(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $parser = new Parser('DROP INDEX idx ON t');
        $result = $guard->classifyStatement($parser->statements[0]);
        self::assertNull($result);
    }

    public function testClassifyStatementReturnsNullForAlterNonTableDirectly(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        $parser = new Parser('ALTER DATABASE test CHARACTER SET utf8');
        $result = $guard->classifyStatement($parser->statements[0]);
        self::assertNull($result);
    }

    public function testClassifyWithFallbackNestedSubquerySkipsKeywordsInParens(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(
            QueryKind::READ,
            $guard->classify('WITH cte AS (SELECT 1, (SELECT 2)) SELECT * FROM cte')
        );
    }

    public function testClassifyWithFallbackCaseSensitiveKeywordHandling(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(
            QueryKind::WRITE_SIMULATED,
            $guard->classify('with cte as (select 1) update t set a = 1')
        );
    }

    public function testClassifyWithFallbackSingleQuoteWithBackslashEscape(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(
            QueryKind::WRITE_SIMULATED,
            $guard->classify("WITH cte AS (SELECT 'it\\'s a UPDATE') DELETE FROM t WHERE id = 1")
        );
    }

    public function testClassifyWithFallbackBacktickNotEscapedByBackslash(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(
            QueryKind::READ,
            $guard->classify("WITH `DELETE` AS (SELECT 1) SELECT * FROM `DELETE`")
        );
    }

    public function testClassifyWithFallbackReplaceFallsToWriteSimulated(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(
            QueryKind::WRITE_SIMULATED,
            $guard->classify('WITH `t` AS( SELECT 1 ) REPLACE INTO users (id) VALUES (1)')
        );
    }

    public function testClassifyWithFallbackTruncateFallsToWriteSimulated(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(
            QueryKind::WRITE_SIMULATED,
            $guard->classify('WITH `t` AS( SELECT 1 ) TRUNCATE TABLE users')
        );
    }

    public function testClassifyWithFallbackCreateFallsToDdlSimulated(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(
            QueryKind::DDL_SIMULATED,
            $guard->classify('WITH `t` AS( SELECT 1 ) CREATE TABLE t2 (id INT)')
        );
    }

    public function testClassifyWithFallbackDropFallsToDdlSimulated(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(
            QueryKind::DDL_SIMULATED,
            $guard->classify('WITH `t` AS( SELECT 1 ) DROP TABLE users')
        );
    }

    public function testClassifyWithFallbackAlterFallsToDdlSimulated(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(
            QueryKind::DDL_SIMULATED,
            $guard->classify('WITH `t` AS( SELECT 1 ) ALTER TABLE users ADD COLUMN x INT')
        );
    }

    public function testClassifyWithFallbackSelectAfterCteFallsToRead(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(
            QueryKind::READ,
            $guard->classify('WITH `t` AS( SELECT 1 ) SELECT * FROM users')
        );
    }

    public function testClassifyWithFallbackUnrecognizedKeywordFallsThrough(): void
    {
        $guard = new MySqlQueryGuard(new MySqlParser());
        self::assertSame(
            QueryKind::READ,
            $guard->classify('WITH `t` AS( SELECT 1 ) GRANT ALL ON t TO admin')
        );
    }
}
