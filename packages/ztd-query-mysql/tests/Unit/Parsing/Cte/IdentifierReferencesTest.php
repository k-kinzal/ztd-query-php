<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Cte;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Parsing\Cte\IdentifierReferences;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Cte\HeaderParser::class)]
#[CoversClass(IdentifierReferences::class)]
final class IdentifierReferencesTest extends TestCase
{
    public function testReferencesIdentifierMatchesWholeNames(): void
    {
        $reader = new IdentifierReferences(\ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        self::assertTrue($reader->referencesIdentifier('SELECT * FROM `Users`', 'users'));
        self::assertFalse($reader->referencesIdentifier("SELECT 'users' FROM users_archive", 'users'));
    }

    public function testReferencesAnyIdentifierChecksEveryCandidate(): void
    {
        $reader = new IdentifierReferences(\ZtdQuery\Platform\MySql\MySqlLexerProfile::create());
        self::assertTrue($reader->referencesAnyIdentifier('SELECT * FROM orders', ['users', 'orders']));
        self::assertFalse($reader->referencesAnyIdentifier('SELECT 1', ['users', 'orders']));
        self::assertFalse($reader->referencesAnyIdentifier('SELECT * FROM orders', []));
    }

}
