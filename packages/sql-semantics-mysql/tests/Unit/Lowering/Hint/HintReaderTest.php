<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Hint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Hint\HintReader;

#[CoversClass(HintReader::class)]
#[Small]
final class HintReaderTest extends TestCase
{
    public function testCommentsFindTheCommentAfterEachKeyword(): void
    {
        $sql = 'INSERT /*+ BKA(t) */ INTO t SELECT/*+NO_BKA()*/ a FROM u WHERE a IN (SELECT /* plain */ 1) ON DUPLICATE KEY UPDATE /*+ FOO */ a = 1';
        $comments = (new HintReader(GrammarRelease::MySql847))->comments((new MySqlParser('mysql-8.4.7'))->parse($sql));

        self::assertSame([0, 28, 108], array_keys($comments));
        self::assertSame(['/*+ BKA(`t`) */', '/*+ NO_BKA() */', null], array_map(static fn ($comment): ?string => $comment->text(), array_values($comments)));
        self::assertSame("Optimizer hint syntax error near 'FOO */ a = 1' at line 1", $comments[108]->error?->message($sql));
    }

    public function testCommentsSkipACommentAfterAnotherOne(): void
    {
        self::assertSame([], (new HintReader(GrammarRelease::MySql847))->comments((new MySqlParser('mysql-8.4.7'))->parse('SELECT /* x */ /*+ BKA(x) */ 1')));
    }

    public function testCommentsReadDoubleQuotedNamesUnderAnsiQuotes(): void
    {
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('SELECT /*+ QB_NAME("a b") */ 1');

        self::assertSame('/*+ QB_NAME(`a b`) */', (new HintReader(GrammarRelease::MySql847, true))->comments($tree)[0]->text());
        self::assertNotNull((new HintReader(GrammarRelease::MySql847))->comments($tree)[0]->error);
    }

    public function testCommentsAreOrdinaryCommentsInMySql56(): void
    {
        self::assertSame([], (new HintReader(GrammarRelease::MySql5651))->comments((new MySqlParser('mysql-5.6.51'))->parse('SELECT /*+ BKA(x) */ 1')));
    }
}
