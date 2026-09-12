<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Exception\LexicalException;
use SqlFaker\Sqlite\Generation\Lexeme\JoinModifiers;

#[CoversClass(JoinModifiers::class)]
#[UsesClass(LexicalException::class)]
final class JoinModifiersTest extends TestCase
{
    public function testMaskRecognizesCaseInsensitiveSourceWordsAndFullJoinFlags(): void
    {
        $types = new JoinModifiers();
        self::assertSame($types->mask('LEFT') | $types->mask('RIGHT'), $types->mask('full'));
        self::assertSame($types->mask('INNER') | 32, $types->mask('CROSS'));
    }

    public function testMaskReportsAnUnknownSourceRegistration(): void
    {
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unknown SQLite join modifier: UNREVIEWED');
        (new JoinModifiers())->mask('UNREVIEWED');
    }

    public function testValidRejectsInnerOuterConflictsAndNaturalJoinConditions(): void
    {
        $types = new JoinModifiers();
        self::assertFalse($types->valid($types->mask('LEFT') | $types->mask('INNER'), false));
        self::assertFalse($types->valid($types->mask('OUTER'), false));
        self::assertFalse($types->valid($types->mask('NATURAL'), true));
        self::assertTrue($types->valid($types->mask('FULL') | $types->mask('OUTER'), true));
    }

    public function testCanCompleteConsidersAllReachablePrefixStates(): void
    {
        $types = new JoinModifiers();
        $words = ['INNER', 'CROSS', 'NATURAL', 'LEFT', 'RIGHT', 'FULL', 'OUTER'];
        self::assertFalse($types->canComplete($types->mask('OUTER'), 0, false, $words));
        self::assertTrue($types->canComplete($types->mask('OUTER'), 1, false, $words));
        self::assertFalse($types->canComplete($types->mask('INNER') | $types->mask('OUTER'), 2, false, $words));
        self::assertFalse($types->canComplete($types->mask('NATURAL'), 2, true, $words));
    }
    #[DataProvider('providerModifierPairs')]
    public function testValidChecksNaturalOuterAndDirectionalCombinations(string $first, string $second, bool $hasCondition, bool $valid): void
    {
        $types = new JoinModifiers();
        self::assertSame($valid, $types->valid($types->mask($first) | $types->mask($second), $hasCondition));
    }

    /**
     * @return iterable<array{string, string, bool, bool}>
     */
    public static function providerModifierPairs(): iterable
    {
        yield ['NATURAL', 'OUTER', false, false];
        yield ['NATURAL', 'LEFT', false, true];
        yield ['NATURAL', 'RIGHT', false, true];
        yield ['LEFT', 'OUTER', true, true];
        yield ['RIGHT', 'OUTER', true, true];
        yield ['LEFT', 'CROSS', false, false];
        yield ['FULL', 'INNER', false, false];
        yield ['NATURAL', 'FULL', true, false];
        yield ['NATURAL', 'INNER', false, true];
        yield ['NATURAL', 'INNER', true, false];
    }
}
