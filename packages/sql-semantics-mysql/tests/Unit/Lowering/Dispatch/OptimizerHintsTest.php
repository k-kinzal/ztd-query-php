<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dispatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Dispatch\OptimizerHints;

#[CoversClass(OptimizerHints::class)]
#[Medium]
final class OptimizerHintsTest extends TestCase
{
    public function testFirstFindsTheHintCommentAfterTheStatementKeyword(): void
    {
        $parser = new MySqlParser('mysql-8.4.7');
        $hints = new OptimizerHints();

        self::assertSame('/*+ MAX_EXECUTION_TIME(1000) */ ', $hints->first($parser->parse('SELECT /*+ MAX_EXECUTION_TIME(1000) */ 1')));
        self::assertSame('/*+ SET_VAR(sort_buffer_size = 16M) */ ', $hints->first($parser->parse('SELECT 1 FROM t WHERE a IN (SELECT /*+ SET_VAR(sort_buffer_size = 16M) */ b FROM u)')));
        self::assertNull($hints->first($parser->parse('SELECT 1')));
        self::assertNull($hints->first($parser->parse('/*+ NO_ICP(t) */ SELECT 1')));
        self::assertNull($hints->first($parser->parse('SELECT /* plain */ 1')));
        self::assertNull($hints->first($parser->parse('SELECT 1 /*+ late */')));
    }

    public function testFirstMakesAHintedStatementAMissingRule(): void
    {
        self::assertSame('SELECT 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT /*+ MAX_EXECUTION_TIME(1000) */ 1')->toString());

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL optimizer hints, which the parser delivers as a comment: /*+ MAX_EXECUTION_TIME(1000) */');

        (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT /*+ MAX_EXECUTION_TIME(1000) */ 1');
    }
}
