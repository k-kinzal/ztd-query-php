<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Core\Ast\DialectParser;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;

#[CoversClass(DialectParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\QueryRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[Medium]
final class DialectParserTest extends TestCase
{
    #[TestWith([PostgreSqlDialect::PostgreSql, 'parse_toplevel', 'pg-17.2'])]
    #[TestWith([MySqlDialect::MySql, 'start_entry', 'mysql-8.4.7'])]
    #[TestWith([SqliteDialect::Sqlite, 'input', 'sqlite-3.47.2'])]
    public function testParseUsesTheRequestedGrammarAndRetainsSql(Dialect $dialect, string $root, string $version): void
    {
        $parser = new DialectParser($dialect, $version);
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
        self::assertSame($version, (new DialectParser($dialect))->version());
    }

    #[TestWith([PostgreSqlDialect::PostgreSql])]
    #[TestWith([MySqlDialect::MySql])]
    #[TestWith([SqliteDialect::Sqlite])]
    public function testRejectsAnUnavailableGrammarRelease(Dialect $dialect): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported');
        new DialectParser($dialect, 'unavailable-release');
    }

    public function testParseScriptPreservesStringAndRoutineSemicolons(): void
    {
        $parser = new DialectParser(MySqlDialect::MySql);
        $trees = $parser->parseScript("CREATE PROCEDURE p() BEGIN SELECT ';'; SELECT 2; END; DROP TABLE IF EXISTS t;");
        self::assertCount(2, $trees);
        self::assertStringContainsString("SELECT ';'", $trees[0]->toString());
        self::assertStringContainsString('DROP TABLE', $trees[1]->toString());
    }

    public function testParseScriptRejectsAnInvalidTrailingCommand(): void
    {
        $this->expectException(\SqlParser\Parser\SyntaxException::class);
        (new DialectParser(MySqlDialect::MySql))->parseScript('DROP TABLE t; CREATE TABLE');
    }

}
