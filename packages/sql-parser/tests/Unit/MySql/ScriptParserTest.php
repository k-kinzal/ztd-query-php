<?php

declare(strict_types=1);

namespace Tests\Unit\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlParser\MySql\ScriptParser;
use SqlParser\Parser\LrParser;
use SqlParser\Parser\SyntaxException;
use SqlParser\Resource\VersionRegistry;
use SqlParser\Table\TableFile;

#[CoversClass(ScriptParser::class)]
#[Small]
final class ScriptParserTest extends TestCase
{
    public function testParseDoesNotSplitSemicolonsInStringLiteralsOrComments(): void
    {
        $trees = (new MySqlParser())->parseAll("SELECT ';'; /* ; */ SELECT 2");
        self::assertCount(2, $trees);
        self::assertSame("SELECT ';';", $trees[0]->toString());
        self::assertSame(' /* ; */ SELECT 2', $trees[1]->toString());
    }

    public function testCandidateWaitsForTheRemainderOfACompoundStatement(): void
    {
        $source = 'CREATE PROCEDURE p() BEGIN SELECT 1;';
        $table = (new TableFile())->load((new VersionRegistry())->resolve('mysql')->tablePath);
        $parser = new ScriptParser(new LrParser($table));
        self::assertNull($parser->candidate((new MySqlParser())->tokenize($source), $source));
    }

    public function testCandidateDoesNotHideAnUnexpectedTokenInsideTheStatement(): void
    {
        $source = 'SELECT FROM;';
        $table = (new TableFile())->load((new VersionRegistry())->resolve('mysql')->tablePath);
        $parser = new ScriptParser(new LrParser($table));
        $this->expectException(SyntaxException::class);
        $parser->candidate((new MySqlParser())->tokenize($source), $source);
    }
}
