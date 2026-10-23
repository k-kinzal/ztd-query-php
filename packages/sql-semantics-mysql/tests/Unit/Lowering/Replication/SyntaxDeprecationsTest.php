<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Replication\SyntaxDeprecations;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;

#[CoversClass(SyntaxDeprecations::class)]
#[Medium]
final class SyntaxDeprecationsTest extends TestCase
{
    public function testPrecedingUsesTheAcceptedKeywordAcrossComments(): void
    {
        $parser = new MySqlParser('mysql-8.0.44');
        $sql = '/* head */ START /* gap */ SLAVE';
        $replica = $parser->parse($sql)->find('replica')[0];

        self::assertSame('START_SYM', (new SyntaxDeprecations())->preceding($replica, $parser, $sql)?->name);
    }

    public function testBinaryLogsKeepTheirKeywordNoticeBeforeAnInvalidOperand(): void
    {
        $sql = 'PURGE /* gap */ MASTER LOGS TO';
        $warnings = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.2.0'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql820));

        self::assertEquals([new Deprecation(Deprecated::PurgeMasterLogs)], array_column($warnings, 1));
    }

    /**
     * @return iterable<string, array{GrammarRelease, string, list<Deprecation>}>
     */
    public static function providerKeywords(): iterable
    {
        $command = new Deprecation(Deprecated::ReplicationSpelling, "'CHANGE MASTER' is deprecated and will be removed in a future release. Please use CHANGE REPLICATION SOURCE instead");
        $position = new Deprecation(Deprecated::ReplicationSpelling, "'MASTER_LOG_POS' is deprecated and will be removed in a future release. Please use SOURCE_LOG_POS instead");
        $host = new Deprecation(Deprecated::ReplicationSpelling, "'MASTER_HOST' is deprecated and will be removed in a future release. Please use SOURCE_HOST instead");
        yield 'both keywords before invalid value' => [GrammarRelease::MySql8044, 'CHANGE MASTER TO MASTER_LOG_POS = 0xb', [$command, $position]];
        yield 'command before missing to' => [GrammarRelease::MySql8044, 'CHANGE MASTER', [$command]];
        yield 'option before missing value' => [GrammarRelease::MySql8044, 'CHANGE REPLICATION SOURCE TO MASTER_HOST=', [$host]];
        yield 'current option' => [GrammarRelease::MySql8044, 'CHANGE REPLICATION SOURCE TO SOURCE_LOG_POS=0xb', []];
        yield 'rejected command' => [GrammarRelease::MySql8044, 'CHANGE ) MASTER TO MASTER_LOG_POS=0xb', []];
        yield 'legacy vocabulary is not deprecated' => [GrammarRelease::MySql5744, 'CHANGE MASTER TO MASTER_LOG_POS=0xb', []];
        yield 'removed vocabulary is not deprecated' => [GrammarRelease::MySql847, 'CHANGE MASTER TO MASTER_LOG_POS=0xb', []];
        $start = new Deprecation(Deprecated::ReplicationSpelling, "'START SLAVE' is deprecated and will be removed in a future release. Please use START REPLICA instead");
        yield 'start before credentials' => [GrammarRelease::MySql8044, 'START SLAVE USER = 0x41', [$start]];
        yield 'start before gtid set' => [GrammarRelease::MySql8044, 'START SLAVE UNTIL SQL_BEFORE_GTIDS = 0x41', [$start]];
        $reset = new Deprecation(Deprecated::ReplicationSpelling, "'RESET SLAVE' is deprecated and will be removed in a future release. Please use RESET REPLICA instead");
        yield 'reset before channel' => [GrammarRelease::MySql8044, 'RESET SLAVE FOR CHANNEL 0x41', [$reset]];
        yield 'reset all before channel' => [GrammarRelease::MySql8044, 'RESET SLAVE ALL FOR CHANNEL 0x41', [$reset]];
        yield 'current reset' => [GrammarRelease::MySql8044, 'RESET REPLICA FOR CHANNEL 0x41', []];
        yield 'stop waits for completion' => [GrammarRelease::MySql8044, 'STOP SLAVE FOR CHANNEL 0x41', []];
        yield 'show waits for completion' => [GrammarRelease::MySql8044, 'SHOW SLAVE STATUS FOR CHANNEL 0x41', []];
    }

    /**
     * @param list<Deprecation> $expected The notices before the refusal
     */
    #[DataProvider('providerKeywords')]
    public function testCompletedUsesTheAcceptedKeywordAndItsRelease(GrammarRelease $release, string $sql, array $expected): void
    {
        $warnings = (new SyntaxWarnings())->completed(new MySqlParser($release->value), $sql, strlen($sql), new LanguageProfile($release));

        self::assertEquals($expected, array_column($warnings, 1));
    }
}
