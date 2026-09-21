<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\Identifier as Subject;
use SqlParser\Lexer\Token;
use SqlParser\PostgreSql\PostgreSqlParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
final class IdentifierTest extends TestCase
{
    public function testDecodeKeepsPlainNamesAndUnfoldsQuotedOnes(): void
    {
        $tree = (new PostgreSqlParser())->parse('CREATE TABLE t (Plain INT, "Quo""ted" INT, name INT)');
        $names = array_map(static fn ($node): string => (new Subject())->decode($node->tokens()[0]), $tree->find('columnDef'));

        self::assertSame(['Plain', 'Quo"ted', 'name'], $names);
        self::assertSame('x', (new Subject())->decode(new Token(1, 'IDENT', 'x', 0)));
    }
}
