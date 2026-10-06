<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Manipulation\Merge;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeMatch::class)]
#[Small]
final class MergeMatchTest extends TestCase
{
    public function testTargetedIsFalseForSourceRowsOnly(): void
    {
        self::assertSame([true, true, false], [\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeMatch::Matched->targeted(), \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeMatch::NotMatchedBySource->targeted(), \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeMatch::NotMatched->targeted()]);
    }
}
