<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
final class IdentifiersTest extends TestCase
{
    public function testUnquoteIdentifier(): void
    {
        self::assertSame('User"Name', (new \ZtdQuery\Platform\Postgres\Parsing\Statement\Identifiers())->unquoteIdentifier('"User""Name"'));
    }

    public function testStripSchemaPrefix(): void
    {
        self::assertSame('"Users"', (new \ZtdQuery\Platform\Postgres\Parsing\Statement\Identifiers())->stripSchemaPrefix('tenant."Users"'));
    }

    public function testParseColumnList(): void
    {
        self::assertSame(['id', 'Name'], (new \ZtdQuery\Platform\Postgres\Parsing\Statement\Identifiers())->parseColumnList('id, "Name"'));
    }

    public function testTruncateIdentifierAt(): void
    {
        $sql = 'tenant."Users"';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(['name' => 'tenant', 'next' => 1], (new \ZtdQuery\Platform\Postgres\Parsing\Statement\Identifiers())->truncateIdentifierAt($stream, 0));
    }
}
