<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Compact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFormatter\Core\Compact\Reductions::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Document::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Grouping::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Keywords::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Renderer::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Rules::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Shape::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Spacing::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Trivia::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Visitor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Formatter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Settings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\MySql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\PostgreSql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\Sqlite\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Facade\DialectFactory::class)]
final class TriviaTest extends TestCase
{
    public function testCleanKeepsOnlyTopLevelDirectives(): void
    {
        $trivia = new \SqlFormatter\Core\Compact\Trivia((new \SqlFormatter\Platform\PostgreSql\Dialect())->compactRules()->nesting);
        self::assertSame('/*+ keep */', $trivia->clean(" -- discard\n/* nested /*+ discard */ body */ /*+ keep */ \n"));
        self::assertFalse($trivia->executable);
    }

    public function testCleanRetainsExecutableMarkersAndBodyWhitespaceAcrossTokens(): void
    {
        $trivia = new \SqlFormatter\Core\Compact\Trivia((new \SqlFormatter\Platform\MySql\Dialect())->compactRules()->nesting);
        self::assertSame('/*!80000 ', $trivia->clean(' /* discard */ /*!80000 '));
        self::assertTrue($trivia->executable);
        self::assertSame('  ', $trivia->clean('  '));
        self::assertSame(' */', $trivia->clean(' */ # discard'));
        self::assertFalse($trivia->executable);
    }

    public function testCleanRecognizesMysqlNestedVersionComments(): void
    {
        $trivia = new \SqlFormatter\Core\Compact\Trivia((new \SqlFormatter\Platform\MySql\Dialect())->compactRules()->nesting);
        self::assertSame('', $trivia->clean('/* outer /*! nested */ /*+ hidden */ */'));
        self::assertSame('/*M! keep */', $trivia->clean('/*M! keep */'));
    }

    public function testBlockEndRespectsTheDialectNestingRules(): void
    {
        self::assertSame(17, (new \SqlFormatter\Core\Compact\Trivia((new \SqlFormatter\Platform\PostgreSql\Dialect())->compactRules()->nesting))->blockEnd('/* a /* b */ c */  ', 0));
        self::assertSame(12, (new \SqlFormatter\Core\Compact\Trivia((new \SqlFormatter\Platform\Sqlite\Dialect())->compactRules()->nesting))->blockEnd('/* a /* b */ c */  ', 0));
    }
    public function testExecutablePartDoesNotCloseTheOuterCommentAtAnInnerComment(): void
    {
        $trivia = new \SqlFormatter\Core\Compact\Trivia((new \SqlFormatter\Platform\MySql\Dialect())->compactRules()->nesting);
        $trivia->executable = true;
        $offset = 0;
        self::assertSame('/* inner */', $trivia->executablePart('/* inner */', $offset));
        self::assertTrue($trivia->executable);
        self::assertSame(11, $offset);
    }
}
