<?php

declare(strict_types=1);

namespace Tests\Unit\Compact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFormatter\Compact\Reductions::class)]
#[CoversClass(\SqlFormatter\Compact\Document::class)]
#[CoversClass(\SqlFormatter\Compact\Grouping::class)]
#[CoversClass(\SqlFormatter\Compact\Keywords::class)]
#[CoversClass(\SqlFormatter\Compact\Renderer::class)]
#[CoversClass(\SqlFormatter\Compact\Rules::class)]
#[CoversClass(\SqlFormatter\Compact\Shape::class)]
#[CoversClass(\SqlFormatter\Compact\Spacing::class)]
#[CoversClass(\SqlFormatter\Compact\Trivia::class)]
#[CoversClass(\SqlFormatter\Compact\Visitor::class)]
final class TriviaTest extends TestCase
{
    public function testCleanKeepsOnlyTopLevelDirectives(): void
    {
        $trivia = new \SqlFormatter\Compact\Trivia(true);
        self::assertSame('/*+ keep */', $trivia->clean(" -- discard\n/* nested /*+ discard */ body */ /*+ keep */ \n"));
        self::assertFalse($trivia->executable);
    }

    public function testCleanRetainsExecutableMarkersAndBodyWhitespaceAcrossTokens(): void
    {
        $trivia = new \SqlFormatter\Compact\Trivia(false, true);
        self::assertSame('/*!80000 ', $trivia->clean(' /* discard */ /*!80000 '));
        self::assertTrue($trivia->executable);
        self::assertSame('  ', $trivia->clean('  '));
        self::assertSame(' */', $trivia->clean(' */ # discard'));
        self::assertFalse($trivia->executable);
    }

    public function testCleanRecognizesMysqlNestedVersionComments(): void
    {
        $trivia = new \SqlFormatter\Compact\Trivia(false, true);
        self::assertSame('', $trivia->clean('/* outer /*! nested */ /*+ hidden */ */'));
        self::assertSame('/*M! keep */', $trivia->clean('/*M! keep */'));
    }

    public function testBlockEndRespectsTheDialectNestingRules(): void
    {
        self::assertSame(17, (new \SqlFormatter\Compact\Trivia(true))->blockEnd('/* a /* b */ c */  ', 0));
        self::assertSame(12, (new \SqlFormatter\Compact\Trivia(false))->blockEnd('/* a /* b */ c */  ', 0));
    }
    public function testExecutablePartDoesNotCloseTheOuterCommentAtAnInnerComment(): void
    {
        $trivia = new \SqlFormatter\Compact\Trivia(false, true);
        $trivia->executable = true;
        $offset = 0;
        self::assertSame('/* inner */', $trivia->executablePart('/* inner */', $offset));
        self::assertTrue($trivia->executable);
        self::assertSame(11, $offset);
    }
}
