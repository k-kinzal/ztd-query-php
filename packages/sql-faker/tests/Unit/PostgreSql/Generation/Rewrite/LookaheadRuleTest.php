<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\LookaheadRule;

#[CoversClass(LookaheadRule::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgLookahead::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class LookaheadRuleTest extends TestCase
{
    public function testRewriteResolvesAliasesFromTheActualFollowersAndRecordsChanges(): void
    {
        $input = TerminalSequence::fromNames(['WITH_LA', 'IDENT', 'WITH', 'ORDINALITY']);
        $result = (new LookaheadRule())->rewrite($input);
        self::assertSame(['WITH', 'IDENT', 'WITH_LA', 'ORDINALITY'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertCount(2, $result->rewrites);
        self::assertSame($result, (new LookaheadRule())->rewrite($result));
    }
}
