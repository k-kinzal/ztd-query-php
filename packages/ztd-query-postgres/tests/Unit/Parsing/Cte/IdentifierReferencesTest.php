<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Cte;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\IdentifierReferences::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\HeaderParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
final class IdentifierReferencesTest extends TestCase
{
    public function testReferencesIdentifier(): void
    {
        self::assertSame(true, (new \ZtdQuery\Platform\Postgres\Parsing\Cte\IdentifierReferences())->referencesIdentifier('SELECT * FROM users', 'users'));
    }

    public function testReferencesAnyIdentifier(): void
    {
        self::assertSame(true, (new \ZtdQuery\Platform\Postgres\Parsing\Cte\IdentifierReferences())->referencesAnyIdentifier('SELECT * FROM users', ['orders', 'users']));
    }
}
