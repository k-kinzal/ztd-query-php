<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Script;

use MySqlMemory\Instance;
use MySqlMemory\Session\Script\Trailing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Trailing::class)]
#[Small]
final class TrailingTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function providerTails(): iterable
    {
        yield 'none' => [[], []];
        yield 'whitespace' => [['SELECT 1;  '], ['SELECT 1;  ']];
        yield 'block comment' => [['SELECT 1; /* c */'], ['SELECT 1;', ' /* c */']];
        yield 'line comment' => [['SELECT 1; -- c'], ['SELECT 1;', ' -- c']];
        yield 'no terminator' => [['SELECT 1 /* c */'], ['SELECT 1 /* c */']];
        yield 'quoted semicolon' => [["SELECT ';' /* c */"], ["SELECT ';' /* c */"]];
        yield 'compound statement' => [['CREATE PROCEDURE p() BEGIN SELECT 1; END; /* c */'], ['CREATE PROCEDURE p() BEGIN SELECT 1; END;', ' /* c */']];
    }

    /**
     * @param list<string> $statements
     * @param list<string> $expected
     */
    #[DataProvider('providerTails')]
    public function testStatementsSeparatesOnlyCommentsAfterATerminator(array $statements, array $expected): void
    {
        self::assertSame($expected, (new Trailing())->statements($statements, (new Instance())->connect()->semantics()->parser()));
    }
}
