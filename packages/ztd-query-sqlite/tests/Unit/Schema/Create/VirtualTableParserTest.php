<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Create;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Sqlite\Schema\Create\VirtualTableParser;

#[CoversClass(VirtualTableParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Expression\AssignmentColumnParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Expression\AssignmentParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Expression\ValueListParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Insert\InsertClauseParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\ExpressionSpan::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\IdentifierDecoder::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\LiteralMasker::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\OpaqueSqlSpan::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\QuotedSpan::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\TopLevelKeywordScanner::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Relation\RelationSourceParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Statement\StatementClassifier::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Statement\StatementStructure::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Statement\TargetTableParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Update\UpdateClauseParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteLexerProfile::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteLexicalMasker::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteSelectRelationParser::class)]
final class VirtualTableParserTest extends TestCase
{
    public function testParseFts5VirtualTableKeepsColumnsAndSkipsModuleOptions(): void
    {
        $parser = new VirtualTableParser();
        $definition = $parser->parseFts5VirtualTable("CREATE VIRTUAL TABLE docs USING fts5(title, body UNINDEXED, tokenize='unicode61')");
        self::assertNotNull($definition);
        self::assertSame(['title', 'body'], $definition->columns);
        self::assertSame(['title' => 'TEXT', 'body' => 'TEXT'], $definition->columnTypes);
        self::assertNull($parser->parseFts5VirtualTable("CREATE VIRTUAL TABLE docs USING fts5(tokenize='unicode61')"));
        self::assertNull($parser->parseFts5VirtualTable('CREATE VIRTUAL TABLE docs USING fts5(body INVALID)'));
        self::assertNull($parser->parseFts5VirtualTable('CREATE TABLE docs(body TEXT)'));
    }

    public function testBodyRequiresOneFts5ModuleAndCompleteFraming(): void
    {
        $parser = new VirtualTableParser();
        self::assertSame('title, body', $parser->body('CREATE VIRTUAL TABLE docs USING fts5(title, body);'));
        self::assertNull($parser->body('CREATE VIRTUAL TABLE docs USING fts4(body)'));
        self::assertNull($parser->body('CREATE VIRTUAL TABLE docs USING fts5(body) trailing'));
        self::assertNull($parser->body('CREATE VIRTUAL TABLE docs USING fts5(body'));
        self::assertNull($parser->body('CREATE VIRTUAL TABLE docs'));
        self::assertNull($parser->body('CREATE VIRTUAL TABLE docs USING fts5(body) USING fts5(x)'));
    }

}
