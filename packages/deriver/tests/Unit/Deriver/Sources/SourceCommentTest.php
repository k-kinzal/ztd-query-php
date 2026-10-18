<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Sources;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class SourceCommentTest extends TestCase
{
    public function testSourceRangeAndTextRemainUninterpreted(): void
    {
        $source = new \Deriver\Deriver\Sources\SourceRef('s', 'x.php', 20, 30);
        $comment = new \Deriver\Deriver\Sources\SourceComment($source, 'raw text');
        self::assertSame($source, $comment->source);
        self::assertSame('raw text', $comment->text);
    }
}
