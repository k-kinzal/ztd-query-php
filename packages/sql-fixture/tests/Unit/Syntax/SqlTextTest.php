<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Syntax\SqlText as Subject;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Subject::class)]
final class SqlTextTest extends TestCase
{
    public function testOfNodeWritesWhatWasWrittenInsideTheNode(): void
    {
        $sql = "CREATE TABLE t (a INT DEFAULT (1 /* one */ + 2),\n  b INT)";
        $tree = (new SqliteParser())->parse($sql);

        self::assertSame('1 /* one */ + 2', (new Subject())->ofNode($tree->find('ccons')[0]->find('expr')[0]));
        self::assertSame('DEFAULT (1 /* one */ + 2)', (new Subject())->ofNode($tree->find('ccons')[0]));
        self::assertSame('a INT', (new Subject())->ofNode($tree->find('columnname')[0]));
    }

    public function testOfNodeDropsTheTriviaWrittenBeforeTheNode(): void
    {
        $sql = 'CREATE TABLE t (a   INT)';
        $tree = (new SqliteParser())->parse($sql);
        $type = $tree->find('typetoken')[0];

        self::assertSame('   INT', $type->toString());
        self::assertSame('INT', (new Subject())->ofNode($type));
    }

    public function testOfNodeAnswersNothingForANodeWithoutTokens(): void
    {
        self::assertSame('', (new Subject())->ofNode(new Node('empty', 0, [])));
    }

    public function testOfTokensKeepsTheTriviaBetweenTheTokens(): void
    {
        $tokens = [new Token(1, 'ID', 'a', 0, '  '), new Token(2, 'PLUS', '+', 4, ' '), new Token(3, 'INTEGER', '1', 6, ' ')];

        self::assertSame('a + 1', (new Subject())->ofTokens($tokens));
        self::assertSame('', (new Subject())->ofTokens([]));
    }
}
