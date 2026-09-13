<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\ForeignKey;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Schema\ForeignKey\DefinitionEntry::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\ForeignKey\DefinitionTokens::class)]
final class DefinitionEntryTest extends TestCase
{
    public function testParseEntry(): void
    {
        $sql = 'FOREIGN KEY (parent_id) REFERENCES parents (id) ON DELETE CASCADE';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertEquals(['name' => 'fk_parent', 'foreignKey' => new \ZtdQuery\Schema\ForeignKeyDefinition(columns: ['parent_id'], referencedTable: 'parents', referencedColumns: ['id'], onDelete: \ZtdQuery\Schema\ReferentialAction::Cascade, onUpdate: \ZtdQuery\Schema\ReferentialAction::NoAction)], (new \ZtdQuery\Platform\Postgres\Schema\ForeignKey\DefinitionEntry())->parseEntry($stream, 'fk_parent', null));
    }

    public function testForeignKeyColumns(): void
    {
        $sql = 'FOREIGN KEY (parent_id) REFERENCES parents (id) ON DELETE CASCADE';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(['parent_id'], (new \ZtdQuery\Platform\Postgres\Schema\ForeignKey\DefinitionEntry())->foreignKeyColumns($stream, $tokens, 5));
    }

    public function testReferencedRelation(): void
    {
        $sql = 'FOREIGN KEY (parent_id) REFERENCES parents (id) ON DELETE CASCADE';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(['table' => 'parents', 'columns' => ['id']], (new \ZtdQuery\Platform\Postgres\Schema\ForeignKey\DefinitionEntry())->referencedRelation($stream, $tokens, 6));
    }

    public function testIdentifierList(): void
    {
        $sql = 'FOREIGN KEY (parent_id) REFERENCES parents (id) ON DELETE CASCADE';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(['parent_id'], (new \ZtdQuery\Platform\Postgres\Schema\ForeignKey\DefinitionEntry())->identifierList($stream, $tokens, 2));
    }

    public function testAction(): void
    {
        $sql = 'FOREIGN KEY (parent_id) REFERENCES parents (id) ON DELETE CASCADE';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(\ZtdQuery\Schema\ReferentialAction::Cascade, (new \ZtdQuery\Platform\Postgres\Schema\ForeignKey\DefinitionEntry())->action($tokens, 'DELETE'));
    }
}
