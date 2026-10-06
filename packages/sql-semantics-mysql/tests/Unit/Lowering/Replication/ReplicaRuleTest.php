<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Replication\ReplicaRule;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(ReplicaRule::class)]
#[Medium]
final class ReplicaRuleTest extends TestCase
{
    public function testStatementLowersEveryGeneration(): void
    {
        self::assertSame('STOP SLAVE IO_THREAD', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('stop slave io_thread')->toString());
        self::assertSame('START REPLICA', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('start replica')->toString());
    }

    public function testLegacyStartLowersTheSplitParts(): void
    {
        self::assertSame("START SLAVE SQL_THREAD UNTIL SQL_AFTER_MTS_GAPS FOR CHANNEL 'c'", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("start slave sql_thread until sql_after_mts_gaps for channel 'c'")->toString());
    }

    public function testStartLowersTheConnectionOptions(): void
    {
        self::assertSame("START SLAVE PASSWORD = 'p' PLUGIN_DIR = 'd'", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("start slave password = 'p' plugin_dir = 'd'")->toString());
    }

    public function testConnectionAnswersTheFourOptions(): void
    {
        self::assertSame("START SLAVE USER = 'u' DEFAULT_AUTH = 'a'", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("start slave user = 'u' default_auth = 'a'")->toString());
    }

    public function testOptionLowersAWrittenOption(): void
    {
        self::assertSame("START REPLICA PLUGIN_DIR = 'd'", (new Semantics(Dialect::MySql))->analyze("start replica plugin_dir = 'd'")->toString());
    }

    public function testThreadsLowersTheListInOrder(): void
    {
        self::assertSame('START REPLICA IO_THREAD, SQL_THREAD, IO_THREAD', (new Semantics(Dialect::MySql))->analyze('start replica io_thread, sql_thread, relay_thread')->toString());
    }

    public function testNodeRejectsAToken(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new ReplicaRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('a START statement part that is not a nonterminal');

        $rule->node(new Token(0, 'USER', 'USER', 0));
    }
}
