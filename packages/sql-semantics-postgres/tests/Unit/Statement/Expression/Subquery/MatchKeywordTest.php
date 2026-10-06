<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Subquery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\MatchKeyword;

#[CoversClass(MatchKeyword::class)]
#[Small]
final class MatchKeywordTest extends TestCase
{
    public function testOperatorIsTheOperatorTheKeywordStandsFor(): void
    {
        self::assertSame(['~~', '!~~', '~~*', '!~~*'], array_map(static fn (MatchKeyword $keyword): string => $keyword->operator(), MatchKeyword::cases()));
    }
}
