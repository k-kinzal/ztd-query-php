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
