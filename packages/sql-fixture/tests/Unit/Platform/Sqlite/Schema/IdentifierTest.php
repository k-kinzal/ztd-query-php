<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\Schema\Identifier as Subject;
use SqlParser\Lexer\Token;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
final class IdentifierTest extends TestCase
{
    public function testDecodeUnfoldsEveryQuotingStyle(): void
    {
        $tree = (new SqliteParser())->parse('CREATE TABLE t (plain INT, "dq""x" INT, [br x] INT, `bt``x` INT, \'st\'\'x\' INT)');
        $names = array_map(static fn ($node): string => (new Subject())->decode($node->tokens()[0]), $tree->find('columnname'));

        self::assertSame(['plain', 'dq"x', 'br x', 'bt`x', "st'x"], $names);
    }

    public function testDecodeKeepsTokensWithoutDelimiters(): void
    {
        self::assertSame('ROWID', (new Subject())->decode(new Token(1, 'ID', 'ROWID', 0)));
    }
}
