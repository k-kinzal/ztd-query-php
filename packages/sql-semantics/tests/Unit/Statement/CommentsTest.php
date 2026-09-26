<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Assertion;
use SqlSemantics\Statement\Comments;

#[CoversClass(Comments::class)]
#[UsesClass(Assertion::class)]
#[Small]
final class CommentsTest extends TestCase
{
    public function testBeforeAnswersTheCommentsOfASymbolPositionInWritingOrder(): void
    {
        $comments = new Comments([1 => ['-- first', '/* second */'], 3 => ['#third']]);
        self::assertSame(['-- first', '/* second */'], $comments->before(1));
        self::assertSame(['#third'], $comments->before(3));
        self::assertSame([], $comments->before(0));
        self::assertSame([], (new Comments())->before(0));
    }

    public function testPositionsListsTheCommentedSymbolsInAscendingOrder(): void
    {
        self::assertSame([], (new Comments())->positions());
        self::assertSame([0, 2, 5], (new Comments([5 => ['-- a'], 0 => ['-- b'], 2 => ['-- c']]))->positions());
    }

    public function testWithAddsCommentsAfterThoseAlreadyAtThePositionAndPreservesTheOriginal(): void
    {
        $original = new Comments([0 => ['-- a']]);
        $updated = $original->with(0, '-- b', '/* c */')->with(2, '*/');
        self::assertSame(['-- a'], $original->before(0));
        self::assertSame([0], $original->positions());
        self::assertSame(['-- a', '-- b', '/* c */'], $updated->before(0));
        self::assertSame(['*/'], $updated->before(2));
        self::assertSame([0, 2], $updated->positions());
    }
}
