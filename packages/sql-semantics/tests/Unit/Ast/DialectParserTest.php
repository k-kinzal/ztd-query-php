<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Dialect;

#[CoversClass(DialectParser::class)]
#[Medium]
final class DialectParserTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'parse_toplevel', 'pg-17.2'])]
    #[TestWith([Dialect::MySql, 'start_entry', 'mysql-8.4.7'])]
    #[TestWith([Dialect::Sqlite, 'input', 'sqlite-3.47.2'])]
    public function testParseUsesTheRequestedGrammarAndRetainsSql(Dialect $dialect, string $root, string $version): void
    {
        $parser = new DialectParser($dialect, $version);
        $sql = '/* text */ SELECT 1;';
        $tree = $parser->parse($sql);
        self::assertSame($root, $tree->name);
        self::assertSame($sql, $tree->toString());
    }

    #[TestWith([Dialect::PostgreSql, 'pg-17.2'])]
    #[TestWith([Dialect::MySql, 'mysql-8.4.7'])]
    #[TestWith([Dialect::Sqlite, 'sqlite-3.47.2'])]
    public function testVersionReturnsTheResolvedRelease(Dialect $dialect, string $version): void
    {
        self::assertSame($version, (new DialectParser($dialect))->version());
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testRejectsAnUnavailableGrammarRelease(Dialect $dialect): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported');
        new DialectParser($dialect, 'unavailable-release');
    }
}
