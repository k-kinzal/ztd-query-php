<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFaker\PostgreSql\PgLookahead;

#[CoversClass(PgLookahead::class)]
final class PgLookaheadTest extends TestCase
{
    #[DataProvider('providerLookahead')]
    public function testAppliedSubstitutesWhenTheFollowerCallsForIt(PgLookahead $lookahead): void
    {
        self::assertSame(['NOT_LA', 'NULL_P'], $lookahead->applied(['NOT', 'NULL_P']));
    }

    #[DataProvider('providerLookahead')]
    public function testAppliedLeavesTheTokenAloneForAnotherFollower(PgLookahead $lookahead): void
    {
        self::assertSame(['NOT', 'IDENT'], $lookahead->applied(['NOT', 'IDENT']));
    }

    #[DataProvider('providerLookahead')]
    public function testAppliedLeavesTheLastTokenAloneBecauseNothingFollowsIt(PgLookahead $lookahead): void
    {
        self::assertSame(['NOT'], $lookahead->applied(['NOT']));
    }

    #[DataProvider('providerLookahead')]
    public function testAppliedLeavesATokenThatTriggersNothing(PgLookahead $lookahead): void
    {
        self::assertSame(['SELECT', 'IDENT'], $lookahead->applied(['SELECT', 'IDENT']));
    }

    #[DataProvider('providerLookahead')]
    public function testNormalizedSettlesTheBaseTokenOnItsSubstitute(PgLookahead $lookahead): void
    {
        self::assertSame(['NOT_LA', 'NULL_P'], $lookahead->normalized(['NOT', 'NULL_P']));
    }

    #[DataProvider('providerLookahead')]
    public function testNormalizedWalksASubstitutionBackWhenTheFollowerDoesNotCallForIt(
        PgLookahead $lookahead,
    ): void {
        self::assertSame(['NOT', 'IDENT'], $lookahead->normalized(['NOT_LA', 'IDENT']));
    }

    #[DataProvider('providerLookahead')]
    public function testNormalizedLeavesATerminalThatTriggersNothing(PgLookahead $lookahead): void
    {
        self::assertSame(['SELECT', 'IDENT'], $lookahead->normalized(['SELECT', 'IDENT']));
    }

    #[DataProvider('providerLookahead')]
    public function testBaseOfReportsTheTokenASubstituteIsSpelledAs(PgLookahead $lookahead): void
    {
        self::assertSame('NOT', $lookahead->baseOf('NOT_LA'));
    }

    #[DataProvider('providerLookahead')]
    public function testBaseOfReportsNothingForATokenThatSubstitutesForNone(PgLookahead $lookahead): void
    {
        self::assertNull($lookahead->baseOf('SELECT'));
    }

    public function testAppliedFindsNothingToDoWithoutRules(): void
    {
        self::assertSame(['NOT', 'NULL_P'], (new PgLookahead([]))->applied(['NOT', 'NULL_P']));
    }

    /**
     * @return iterable<string, array{PgLookahead}>
     */
    public static function providerLookahead(): iterable
    {
        yield 'NOT before NULL' => [new PgLookahead([
            'NOT' => ['token' => 'NOT_LA', 'followed_by' => ['NULL_P', 'IN_P']],
        ])];
    }
}
