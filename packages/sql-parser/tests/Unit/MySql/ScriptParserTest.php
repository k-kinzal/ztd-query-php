<?php

declare(strict_types=1);

namespace Tests\Unit\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlParser\MySql\ScriptParser;
use SqlParser\Parser\LrParser;
use SqlParser\Parser\SyntaxException;
use SqlParser\Resource\VersionRegistry;
use SqlParser\Table\TableFile;

#[CoversClass(ScriptParser::class)]
#[Small]
#[UsesClass(MySqlParser::class)]
#[UsesClass(LrParser::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(VersionRegistry::class)]
#[UsesClass(TableFile::class)]
#[UsesClass(\SqlParser\Grammar\SymbolTable::class)]
#[UsesClass(\SqlParser\Lexer\Cursor::class)]
#[UsesClass(\SqlParser\Lexer\Lexeme::class)]
#[UsesClass(\SqlParser\Lexer\LexicalException::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Lexer\TerminalIndex::class)]
#[UsesClass(\SqlParser\Lexer\Token::class)]
#[UsesClass(\SqlParser\MySql\Lexer\KeywordTable::class)]
#[UsesClass(\SqlParser\MySql\Lexer\MySqlLexer::class)]
#[UsesClass(\SqlParser\MySql\Lexer\NumberScanner::class)]
#[UsesClass(\SqlParser\MySql\Lexer\OperatorScanner::class)]
#[UsesClass(\SqlParser\MySql\Lexer\QuotedScanner::class)]
#[UsesClass(\SqlParser\MySql\Lexer\Scan::class)]
#[UsesClass(\SqlParser\MySql\Lexer\TriviaScanner::class)]
#[UsesClass(\SqlParser\MySql\Lexer\VariableScanner::class)]
#[UsesClass(\SqlParser\MySql\Lexer\WordScanner::class)]
#[UsesClass(\SqlParser\MySql\MySqlVersion::class)]
#[UsesClass(\SqlParser\MySql\SqlMode::class)]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\Node::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Resource\SqlVersion::class)]
#[UsesClass(\SqlParser\Table\ActionCode::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
#[UsesClass(\SqlParser\Table\PackedRows::class)]
#[UsesClass(\SqlParser\Table\ParseTable::class)]
#[UsesClass(\SqlParser\Table\TableCodec::class)]
#[UsesClass(\SqlParser\Table\TableRule::class)]
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
