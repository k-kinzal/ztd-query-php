<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\SourceComments;

#[CoversClass(SourceComments::class)]
#[Small]
final class SourceCommentsTest extends TestCase
{
    public function testBeforeAnswersTheCommentsOfTheTokenTheyPrecede(): void
    {
        $select = new Token(1, 'SELECT', 'SELECT', 0);
        $one = new Token(2, 'NUM', '1', 15, ' /* value */ ');
        $comments = new SourceComments(['-- lead'], [spl_object_id($one) => ['/* value */']], ['-- end']);
        self::assertSame(['/* value */'], $comments->before($one));
        self::assertSame([], $comments->before($select));
        self::assertSame(['-- lead'], $comments->leading);
        self::assertSame(['-- end'], $comments->trailing);
    }

    public function testFirstFindsTheFirstTokenBelowANodeAndNullForAnEmptyNode(): void
    {
        $empty = new Node('opt_clause', 0, []);
        $one = new Token(2, 'NUM', '1', 7);
        $tree = new Node('root', 0, [$empty, new Node('expr', 0, [new Node('term', 0, [$one])]), new Token(3, ';', ';', 8)]);
        $comments = new SourceComments([], [], []);
        self::assertNull($comments->first($empty));
        self::assertSame($one, $comments->first($tree));
    }
}
