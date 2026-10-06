<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule::class)]
#[Medium]
final class DefinitionRuleTest extends TestCase
{
    public function testCasesHoldTheMessagesOfTheServer(): void
    {
        self::assertSame('MATCH PARTIAL not yet implemented', \SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule::MatchPartial->value);
    }
}
