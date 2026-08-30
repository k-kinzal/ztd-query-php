<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\ParserSemantics;

#[CoversClass(ParserSemantics::class)]
final class ParserSemanticsTest extends TestCase
{
    public function testAppliedDropsTheQualificationInFrontOfAUserVariable(): void
    {
        self::assertSame(
            ['IDENT', '@', 'IDENT'],
            (new ParserSemantics())->applied(['IDENT', '.', 'IDENT', '@', 'IDENT']),
        );
    }

    public function testAppliedDropsTheEmptyCallAfterCurrentUser(): void
    {
        self::assertSame(
            ['CURRENT_USER', ':'],
            (new ParserSemantics())->applied(['CURRENT_USER', '(', ')', ':']),
        );
    }

    public function testAppliedGivesAnAlterEventTheClauseTheParserRequires(): void
    {
        self::assertSame(
            ['ALTER_SYM', 'EVENT_SYM', 'IDENT', 'ENABLE_SYM'],
            (new ParserSemantics())->applied(['ALTER_SYM', 'EVENT_SYM', 'IDENT']),
        );
    }

    public function testAppliedSpellsEqualsAsTheParserDoesInFrontOfAQuantifier(): void
    {
        self::assertSame(['EQ', 'ALL'], (new ParserSemantics())->applied(['EQUAL_SYM', 'ALL']));
    }

    public function testAppliedDropsAReleaseThatFollowsAChainWithNoNoBeforeIt(): void
    {
        self::assertSame(['CHAIN_SYM'], (new ParserSemantics())->applied(['CHAIN_SYM', 'RELEASE_SYM']));
    }

    public function testAppliedKeepsAReleaseThatFollowsANoChain(): void
    {
        self::assertSame(
            ['NO_SYM', 'CHAIN_SYM', 'RELEASE_SYM'],
            (new ParserSemantics())->applied(['NO_SYM', 'CHAIN_SYM', 'RELEASE_SYM']),
        );
    }

    public function testAppliedSpellsANumberAsTheParserDoesAfterSystem(): void
    {
        self::assertSame(['SYSTEM', 'NUM'], (new ParserSemantics())->applied(['SYSTEM', 'DECIMAL_NUM']));
    }

    public function testWithoutQualifiedUserVariableDropsTheNameInFrontOfIt(): void
    {
        $terminals = (new ParserSemantics())->withoutQualifiedUserVariable(['db', '.', 'tbl', '.', 'name', '@', 'var']);

        self::assertSame(['name', '@', 'var'], $terminals);
    }

    public function testWithoutQualifiedUserVariableLeavesTerminalsThatNameNoVariable(): void
    {
        $terminals = (new ParserSemantics())->withoutQualifiedUserVariable(['db', '.', 'tbl']);

        self::assertSame(['db', '.', 'tbl'], $terminals);
    }

    public function testWithoutCurrentUserCallWritesTheKeywordAlone(): void
    {
        $terminals = (new ParserSemantics())->withoutCurrentUserCall(['CURRENT_USER', '(', ')', ':']);

        self::assertSame(['CURRENT_USER', ':'], $terminals);
    }

    public function testWithoutCurrentUserCallKeepsACallNothingFollows(): void
    {
        $terminals = (new ParserSemantics())->withoutCurrentUserCall(['CURRENT_USER', '(', ')']);

        self::assertSame(['CURRENT_USER', '(', ')'], $terminals);
    }

    public function testWithEventClauseWritesOneWhereTheStatementHasNone(): void
    {
        $terminals = (new ParserSemantics())->withEventClause(['ALTER_SYM', 'EVENT_SYM', 'name']);

        self::assertSame(['ALTER_SYM', 'EVENT_SYM', 'name', 'ENABLE_SYM'], $terminals);
    }

    public function testWithEventClauseLeavesAStatementThatIsNotAnAlterEvent(): void
    {
        $terminals = (new ParserSemantics())->withEventClause(['DROP_SYM', 'EVENT_SYM', 'name']);

        self::assertSame(['DROP_SYM', 'EVENT_SYM', 'name'], $terminals);
    }

    public function testRespelledWritesEqualsAsTheTokenAQuantifierFollows(): void
    {
        $terminals = (new ParserSemantics())->respelled(['a', 'EQUAL_SYM', 'ALL']);

        self::assertSame(['a', 'EQ', 'ALL'], $terminals);
    }

    public function testRespelledDropsReleaseAfterChainUnlessNoStandsBeforeIt(): void
    {
        self::assertSame(['CHAIN'], (new ParserSemantics())->respelled(['CHAIN', 'RELEASE']));
        self::assertSame(['NO', 'CHAIN', 'RELEASE'], (new ParserSemantics())->respelled(['NO', 'CHAIN', 'RELEASE']));
    }

    public function testRespelledReadsANumberAfterSystemAsAPlainOne(): void
    {
        $terminals = (new ParserSemantics())->respelled(['SYSTEM', 'DECIMAL_NUM']);

        self::assertSame(['SYSTEM', 'NUM'], $terminals);
    }

    /**
     * @param list<string> $terminals
     * @param list<string> $expected
     */
    #[DataProvider('providerUserVariableQualification')]
    public function testWithoutQualifiedUserVariableDropsOnlyWhatQualifiesTheVariable(array $terminals, array $expected): void
    {
        self::assertSame($expected, (new ParserSemantics())->withoutQualifiedUserVariable($terminals));
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function providerUserVariableQualification(): iterable
    {
        yield 'nothing to drop' => [['a', '@', 'v'], ['a', '@', 'v']];
        yield 'one level' => [['db', '.', 'name', '@', 'v'], ['name', '@', 'v']];
        yield 'two levels' => [['a', '.', 'b', '.', 'name', '@', 'v'], ['name', '@', 'v']];
        yield 'no variable at all' => [['db', '.', 'tbl'], ['db', '.', 'tbl']];
        yield 'variable first' => [['@', 'v'], ['@', 'v']];
        yield 'dot too far back to belong to it' => [['.', 'a', 'b', '@', 'v'], ['.', 'a', 'b', '@', 'v']];
    }

    /**
     * @param list<string> $terminals
     * @param list<string> $expected
     */
    #[DataProvider('providerCurrentUserCall')]
    public function testWithoutCurrentUserCallWritesTheKeywordAloneOnlyBeforeAColon(array $terminals, array $expected): void
    {
        self::assertSame($expected, (new ParserSemantics())->withoutCurrentUserCall($terminals));
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function providerCurrentUserCall(): iterable
    {
        yield 'bare keyword spelling' => [['CURRENT_USER', '(', ')', ':'], ['CURRENT_USER', ':']];
        yield 'symbol spelling' => [['CURRENT_USER_SYM', '(', ')', ':'], ['CURRENT_USER_SYM', ':']];
        yield 'nothing follows the call' => [['CURRENT_USER', '(', ')'], ['CURRENT_USER', '(', ')']];
        yield 'something else follows it' => [['CURRENT_USER', '(', ')', ','], ['CURRENT_USER', '(', ')', ',']];
        yield 'the call carries an argument' => [['CURRENT_USER', '(', 'x', ')', ':'], ['CURRENT_USER', '(', 'x', ')', ':']];
        yield 'another function entirely' => [['USER', '(', ')', ':'], ['USER', '(', ')', ':']];
    }

    /**
     * @param list<string> $terminals
     * @param list<string> $expected
     */
    #[DataProvider('providerEventClause')]
    public function testWithEventClauseWritesOneOnlyWhereTheStatementEndsWithoutOne(array $terminals, array $expected): void
    {
        self::assertSame($expected, (new ParserSemantics())->withEventClause($terminals));
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function providerEventClause(): iterable
    {
        yield 'bare name' => [
            ['ALTER_SYM', 'EVENT_SYM', 'name'],
            ['ALTER_SYM', 'EVENT_SYM', 'name', 'ENABLE_SYM'],
        ];
        yield 'qualified name' => [
            ['ALTER_SYM', 'EVENT_SYM', 'db', '.', 'name'],
            ['ALTER_SYM', 'EVENT_SYM', 'db', '.', 'name', 'ENABLE_SYM'],
        ];
        yield 'already carries a clause' => [
            ['ALTER_SYM', 'EVENT_SYM', 'name', 'DISABLE_SYM'],
            ['ALTER_SYM', 'EVENT_SYM', 'name', 'DISABLE_SYM'],
        ];
        yield 'qualified name already carries a clause' => [
            ['ALTER_SYM', 'EVENT_SYM', 'db', '.', 'name', 'DISABLE_SYM'],
            ['ALTER_SYM', 'EVENT_SYM', 'db', '.', 'name', 'DISABLE_SYM'],
        ];
        yield 'not an alter' => [['DROP_SYM', 'EVENT_SYM', 'name'], ['DROP_SYM', 'EVENT_SYM', 'name']];
        yield 'not an event' => [['ALTER_SYM', 'TABLE_SYM', 'name'], ['ALTER_SYM', 'TABLE_SYM', 'name']];
    }

    /**
     * @param list<string> $terminals
     * @param list<string> $expected
     */
    #[DataProvider('providerRespelling')]
    public function testRespelledWritesEachTokenAsWhatSitsBesideItRequires(array $terminals, array $expected): void
    {
        self::assertSame($expected, (new ParserSemantics())->respelled($terminals));
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function providerRespelling(): iterable
    {
        yield 'equals before ALL' => [['a', 'EQUAL_SYM', 'ALL'], ['a', 'EQ', 'ALL']];
        yield 'equals before ANY_SYM' => [['a', 'EQUAL_SYM', 'ANY_SYM'], ['a', 'EQ', 'ANY_SYM']];
        yield 'equals before SOME' => [['a', 'EQUAL_SYM', 'SOME'], ['a', 'EQ', 'SOME']];
        yield 'equals before anything else' => [['a', 'EQUAL_SYM', 'b'], ['a', 'EQUAL_SYM', 'b']];
        yield 'equals at the end' => [['a', 'EQUAL_SYM'], ['a', 'EQUAL_SYM']];
        yield 'release after chain' => [['CHAIN', 'RELEASE'], ['CHAIN']];
        yield 'release after chain symbol' => [['CHAIN_SYM', 'RELEASE_SYM'], ['CHAIN_SYM']];
        yield 'release after no chain' => [['NO', 'CHAIN', 'RELEASE'], ['NO', 'CHAIN', 'RELEASE']];
        yield 'release after no chain symbol' => [['NO_SYM', 'CHAIN', 'RELEASE'], ['NO_SYM', 'CHAIN', 'RELEASE']];
        yield 'release after something else' => [['WORK', 'RELEASE'], ['WORK', 'RELEASE']];
        yield 'release first of all' => [['RELEASE'], ['RELEASE']];
        yield 'decimal after a colon' => [[':', 'DECIMAL_NUM'], [':', 'NUM']];
        yield 'float after a colon' => [[':', 'FLOAT_NUM'], [':', 'NUM']];
        yield 'decimal after SYSTEM' => [['SYSTEM', 'DECIMAL_NUM'], ['SYSTEM', 'NUM']];
        yield 'decimal after SYSTEM_SYM' => [['SYSTEM_SYM', 'DECIMAL_NUM'], ['SYSTEM_SYM', 'NUM']];
        yield 'decimal after anything else' => [['a', 'DECIMAL_NUM'], ['a', 'DECIMAL_NUM']];
        yield 'decimal first of all' => [['DECIMAL_NUM'], ['DECIMAL_NUM']];
    }
}
