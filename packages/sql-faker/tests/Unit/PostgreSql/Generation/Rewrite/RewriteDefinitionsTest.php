<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\RewriteDefinitions;

#[CoversClass(RewriteDefinitions::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalMappingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TokenRewriter::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\UniqueOptionRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\CopySourceRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\FetchWithTiesRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\FunctionNameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\HashPartitionBoundRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\LimitOffsetRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\LookaheadRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\OperatorArgumentsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\OverlapsArgumentsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\PublicationObjectRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\RelationNameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\TimeZoneIntervalRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\WindowFrameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgLookahead::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class RewriteDefinitionsTest extends TestCase
{
    public function testCreateComposesTheDeclaredSourceRules(): void
    {
        $input = TerminalSequence::fromNames(['NOT', 'LIKE']);
        $result = (new RewriteDefinitions())->create()->rewrite($input);
        self::assertSame(['NOT_LA', 'LIKE'], $result->names());
        self::assertSame($input->original, $result->original);
    }
}
