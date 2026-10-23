<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Preparation;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Preparation\EmptyPrepared;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmptyPrepared::class)]
#[Small]
final class EmptyPreparedTest extends TestCase
{
    #[TestWith([';', 1065])]
    #[TestWith([';;', 1065])]
    #[TestWith(['/* comment */;', 1295])]
    #[TestWith(['; SELECT 1', 1064])]
    #[TestWith([';/* comment */', 1064])]
    #[TestWith([";'unterminated", 1064])]
    #[TestWith(['/* before */;SELECT 1', 1064])]
    public function testCheckUsesTheFirstQueryBoundary(string $sql, int $code): void
    {
        $this->expectExceptionCode($code);
        (new EmptyPrepared())->check($sql, (new Instance())->connect()->semantics()->parser());
    }

    public function testCheckLeavesQuotedSemicolonsAndLeadingCommentsToTheParser(): void
    {
        (new EmptyPrepared())->check("/* comment */ SELECT ';'", (new Instance())->connect()->semantics()->parser());
        self::expectNotToPerformAssertions();
    }

    public function testCheckQuotesTheFirstSemicolonWithoutTrailingTerminators(): void
    {
        $this->expectExceptionMessage("near '; /* after */' at line 1");
        (new EmptyPrepared())->check("/* before */; /* after */ ; \n", (new Instance())->connect()->semantics()->parser());
    }
}
