<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Hint\Comment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintComment;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\BlockNameHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\ExecutionTimeHint;

#[CoversClass(HintComment::class)]
#[Small]
final class HintCommentTest extends TestCase
{
    public function testTextWritesTheHintsInOrder(): void
    {
        self::assertSame('/*+ QB_NAME(`q`) MAX_EXECUTION_TIME(5) */', (new HintComment([new BlockNameHint('q'), new ExecutionTimeHint('5')]))->text());
    }

    public function testTextAnswersNullWithoutHints(): void
    {
        self::assertNull((new HintComment([]))->text());
    }
}
