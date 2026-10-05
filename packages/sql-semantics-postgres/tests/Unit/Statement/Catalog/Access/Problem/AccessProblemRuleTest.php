<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblemRule::class)]
#[Small]
final class AccessProblemRuleTest extends TestCase
{
    public function testCasesSpellTheMessagesOfTheServer(): void
    {
        self::assertSame('role "public" does not exist', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblemRule::PublicRole->value);
        self::assertCount(16, \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblemRule::cases());
    }
}
