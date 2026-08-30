<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSqlLexerProfiles;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Sql\LexicalDelimiters;
use ZtdQuery\Sql\SqlCommentProfile;

#[CoversClass(SqlCommentProfile::class)]
#[UsesClass(LexicalDelimiters::class)]
final class SqlCommentProfileTest extends TestCase
{
    public function testRefusesADelimiterThatSpellsNothing(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::comments(lineCommentPrefixes: ['']);
    }

    public function testStartsLineCommentSeesEveryPrefixTheDialectDeclares(): void
    {
        $profile = FakeSqlLexerProfiles::comments(lineCommentPrefixes: ['--', '#']);

        self::assertSame(
            [true, true, false],
            [
                $profile->startsLineComment('-- a', 0),
                $profile->startsLineComment('x # a', 2),
                $profile->startsLineComment('// a', 0),
            ],
        );
    }

    public function testStartsLineCommentTakesAPrefixThatNeedsWhitespaceOnlyWhereWhitespaceFollows(): void
    {
        $profile = FakeSqlLexerProfiles::comments(lineCommentPrefixes: [], whitespaceDelimitedLineCommentPrefixes: ['--']);

        self::assertSame(
            [true, true, false],
            [
                $profile->startsLineComment('-- a', 0),
                $profile->startsLineComment('--', 0),
                $profile->startsLineComment('--a', 0),
            ],
        );
    }

    public function testBlockCommentAtAnswersTheDelimitersThatOpenAndCloseOne(): void
    {
        $profile = FakeSqlLexerProfiles::comments(blockCommentPairs: ['{-' => '-}']);

        self::assertSame(['{-', '-}'], $profile->blockCommentAt('{- a -}', 0));
    }

    public function testBlockCommentAtAnswersNothingWhereNoCommentOpens(): void
    {
        self::assertNull(FakeSqlLexerProfiles::comments()->blockCommentAt('SELECT', 0));
    }

    public function testSupportsNestedBlockCommentsSaysWhatTheDialectDeclared(): void
    {
        self::assertTrue(FakeSqlLexerProfiles::comments(nestedBlockComments: true)->supportsNestedBlockComments());
    }
}
