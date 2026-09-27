<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Core\Ast\DialectParser;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Language;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;

#[CoversClass(DialectParser::class)]
#[UsesClass(Language::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class DialectParserTest extends TestCase
{
    #[TestWith([PostgreSqlDialect::PostgreSql, 'parse_toplevel', 'pg-17.2'])]
    #[TestWith([MySqlDialect::MySql, 'start_entry', 'mysql-8.4.7'])]
    #[TestWith([SqliteDialect::Sqlite, 'input', 'sqlite-3.47.2'])]
    public function testParseUsesTheRequestedGrammarAndRetainsSql(Dialect $dialect, string $root, string $version): void
    {
        $parser = new DialectParser(new Language($dialect, $version));
        $sql = '/* text */ SELECT 1;';
        $tree = $parser->parse($sql);
        self::assertSame($root, $tree->name);
        self::assertSame($sql, $tree->toString());
    }

    #[TestWith([PostgreSqlDialect::PostgreSql, 'pg-17.2'])]
    #[TestWith([MySqlDialect::MySql, 'mysql-8.4.7'])]
    #[TestWith([SqliteDialect::Sqlite, 'sqlite-3.47.2'])]
    public function testVersionReturnsTheResolvedRelease(Dialect $dialect, string $version): void
    {
        self::assertSame($version, (new DialectParser(new Language($dialect)))->version());
    }

    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testRejectsAnUnavailableGrammarRelease(Dialect $dialect): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported');
        (new DialectParser(new Language($dialect, 'unavailable-release')))->version();
    }

    public function testParseScriptPreservesStringAndRoutineSemicolons(): void
    {
        $parser = new DialectParser(new Language(MySqlDialect::MySql));
        $trees = $parser->parseScript("CREATE PROCEDURE p() BEGIN SELECT ';'; SELECT 2; END; DROP TABLE IF EXISTS t;");
        self::assertCount(2, $trees);
        self::assertStringContainsString("SELECT ';'", $trees[0]->toString());
        self::assertStringContainsString('DROP TABLE', $trees[1]->toString());
    }

    public function testParseScriptRejectsAnInvalidTrailingCommand(): void
    {
        $this->expectException(\SqlParser\Parser\SyntaxException::class);
        (new DialectParser(new Language(MySqlDialect::MySql)))->parseScript('DROP TABLE t; CREATE TABLE');
    }

    /**
     * @param list<string> $expected
     */
    #[TestWith([MySqlDialect::MySql, "SELECT 1; CREATE PROCEDURE p() BEGIN SELECT ';'; SELECT 2; END; -- tail\nSELECT 3", ['SELECT 1;', " CREATE PROCEDURE p() BEGIN SELECT ';'; SELECT 2; END;", " -- tail\nSELECT 3"]])]
    #[TestWith([PostgreSqlDialect::PostgreSql, 'SELECT 1; CREATE RULE r AS ON UPDATE TO t DO (SELECT 1; SELECT 2); SELECT $$;$$;  ', ['SELECT 1;', ' CREATE RULE r AS ON UPDATE TO t DO (SELECT 1; SELECT 2);', ' SELECT $$;$$;  ']])]
    #[TestWith([SqliteDialect::Sqlite, "CREATE TRIGGER t AFTER INSERT ON x BEGIN SELECT 1; SELECT 2; END; SELECT ';'", ['CREATE TRIGGER t AFTER INSERT ON x BEGIN SELECT 1; SELECT 2; END;', " SELECT ';'"]])]
    public function testSplitPartitionsAScriptAtStatementBoundaries(Dialect $dialect, string $sql, array $expected): void
    {
        self::assertSame($expected, (new DialectParser(new Language($dialect)))->split($sql));
        self::assertSame($sql, implode('', $expected));
    }

    public function testSplitOfWhitespaceAndCommentsHasNoStatements(): void
    {
        self::assertSame([], (new DialectParser(new Language(SqliteDialect::Sqlite)))->split("  -- nothing\n"));
        self::assertSame([], (new DialectParser(new Language(MySqlDialect::MySql)))->split(''));
    }

    public function testSplitRejectsAStatementTheGrammarDoesNotAccept(): void
    {
        $this->expectException(\SqlParser\Parser\SyntaxException::class);
        (new DialectParser(new Language(MySqlDialect::MySql)))->split('SELECT 1; SELECT FROM; SELECT 2');
    }
}
