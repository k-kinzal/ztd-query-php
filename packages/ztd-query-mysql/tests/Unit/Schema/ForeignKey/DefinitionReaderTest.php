<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\ForeignKey;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Schema\ForeignKey\DefinitionReader;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\ForeignKey\TokenReader::class)]
#[CoversClass(DefinitionReader::class)]
final class DefinitionReaderTest extends TestCase
{
    public function testTableBody(): void
    {
        $reader = new DefinitionReader();
        $profile = \ZtdQuery\Platform\MySql\MySqlLexerProfile::create();
        self::assertSame('id INT, FOREIGN KEY (id) REFERENCES p (id)', $reader->tableBody('CREATE TABLE c (id INT, FOREIGN KEY (id) REFERENCES p (id))', $profile));
        self::assertNull($reader->tableBody('SELECT 1', $profile));
        self::assertNull($reader->tableBody('CREATE TABLE c', $profile));
    }

    public function testParseEntry(): void
    {
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize('CONSTRAINT fk FOREIGN KEY (pid) REFERENCES p (id) ON DELETE CASCADE ON UPDATE SET NULL', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        $entry = (new DefinitionReader())->parseEntry($stream, 'default', null);
        self::assertNotNull($entry);
        self::assertSame('fk', $entry['name']);
        self::assertSame(['pid'], $entry['foreignKey']->columns);
        self::assertSame('p', $entry['foreignKey']->referencedTable);
        self::assertSame(['id'], $entry['foreignKey']->referencedColumns);
        self::assertSame(\ZtdQuery\Schema\ReferentialAction::Cascade, $entry['foreignKey']->onDelete);
        self::assertSame(\ZtdQuery\Schema\ReferentialAction::SetNull, $entry['foreignKey']->onUpdate);
    }

    public function testForeignKeyColumns(): void
    {
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize('FOREIGN KEY (a, b) REFERENCES p (x, y)', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        self::assertSame(['a', 'b'], (new DefinitionReader())->foreignKeyColumns($stream, $stream->significantTokens(), 8));
    }

    public function testReferencedRelation(): void
    {
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize('db.`parent` (id, code)', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        self::assertSame(['table' => 'parent', 'columns' => ['id', 'code']], (new DefinitionReader())->referencedRelation($stream, $stream->significantTokens(), 0));
    }

    public function testIdentifierList(): void
    {
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize('(`a`, b)', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        self::assertSame(['a', 'b'], (new DefinitionReader())->identifierList($stream, $stream->significantTokens(), 0));
    }

    public function testActionCase1(): void
    {
        $reader = new DefinitionReader();
        $sql = 'CASCADE';
        $expected = \ZtdQuery\Schema\ReferentialAction::Cascade;
        self::assertSame($expected, $reader->action(\ZtdQuery\Sql\SqlTokenStream::tokenize('ON DELETE ' . $sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens(), 'DELETE'));
        self::assertSame(\ZtdQuery\Schema\ReferentialAction::NoAction, $reader->action([], 'DELETE'));
    }

    public function testActionCase2(): void
    {
        $reader = new DefinitionReader();
        $sql = 'RESTRICT';
        $expected = \ZtdQuery\Schema\ReferentialAction::Restrict;
        self::assertSame($expected, $reader->action(\ZtdQuery\Sql\SqlTokenStream::tokenize('ON DELETE ' . $sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens(), 'DELETE'));
        self::assertSame(\ZtdQuery\Schema\ReferentialAction::NoAction, $reader->action([], 'DELETE'));
    }

    public function testActionCase3(): void
    {
        $reader = new DefinitionReader();
        $sql = 'SET NULL';
        $expected = \ZtdQuery\Schema\ReferentialAction::SetNull;
        self::assertSame($expected, $reader->action(\ZtdQuery\Sql\SqlTokenStream::tokenize('ON DELETE ' . $sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens(), 'DELETE'));
        self::assertSame(\ZtdQuery\Schema\ReferentialAction::NoAction, $reader->action([], 'DELETE'));
    }

    public function testActionCase4(): void
    {
        $reader = new DefinitionReader();
        $sql = 'SET DEFAULT';
        $expected = \ZtdQuery\Schema\ReferentialAction::SetDefault;
        self::assertSame($expected, $reader->action(\ZtdQuery\Sql\SqlTokenStream::tokenize('ON DELETE ' . $sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens(), 'DELETE'));
        self::assertSame(\ZtdQuery\Schema\ReferentialAction::NoAction, $reader->action([], 'DELETE'));
    }

    public function testActionCase5(): void
    {
        $reader = new DefinitionReader();
        $sql = 'NO ACTION';
        $expected = \ZtdQuery\Schema\ReferentialAction::NoAction;
        self::assertSame($expected, $reader->action(\ZtdQuery\Sql\SqlTokenStream::tokenize('ON DELETE ' . $sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens(), 'DELETE'));
        self::assertSame(\ZtdQuery\Schema\ReferentialAction::NoAction, $reader->action([], 'DELETE'));
    }

    public function testKeywordIndex(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('FOREIGN KEY (a) REFERENCES p (id)', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertSame(5, DefinitionReader::keywordIndex($tokens, 'REFERENCES'));
        self::assertNull(DefinitionReader::keywordIndex($tokens, 'DELETE'));
    }

    public function testSymbolIndex(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('(a) REFERENCES p (id)', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertSame(5, DefinitionReader::symbolIndex($tokens, '(', 1));
        self::assertNull(DefinitionReader::symbolIndex($tokens, ',', 0));
    }

}
