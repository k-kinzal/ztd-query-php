<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinKind::class)]
#[Small]
final class JoinKindTest extends TestCase
{
    public function testExtendsRightForLeftAndFullJoins(): void
    {
        self::assertSame([false, false, true, false, true], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinKind $kind): bool => $kind->extendsRight(), \SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinKind::cases()));
    }

    public function testExtendsLeftForRightAndFullJoins(): void
    {
        self::assertSame([false, false, false, true, true], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinKind $kind): bool => $kind->extendsLeft(), \SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinKind::cases()));
    }

    public function testKeywordsLeaveInnerUnwritten(): void
    {
        self::assertSame([[], ['CROSS']], [\SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinKind::Inner->keywords(), \SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinKind::Cross->keywords()]);
    }
}
