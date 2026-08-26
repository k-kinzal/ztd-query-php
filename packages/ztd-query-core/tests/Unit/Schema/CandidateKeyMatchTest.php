<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\CandidateKeyMatch;

#[CoversClass(CandidateKeyMatch::class)]
final class CandidateKeyMatchTest extends TestCase
{
    public function testOfReadsWhatTheRowCarriesInTheKeyColumns(): void
    {
        self::assertSame(
            ['tenant' => 'acme', 'id' => 1],
            (new CandidateKeyMatch())->of(['id' => 1, 'tenant' => 'acme', 'name' => 'a'], ['tenant', 'id']),
        );
    }

    public function testOfAnswersNothingWhereTheRowLacksAKeyColumn(): void
    {
        self::assertNull((new CandidateKeyMatch())->of(['id' => 1], ['id', 'tenant']));
    }

    public function testOfAnswersNothingWhereTheRowLeavesAKeyColumnNull(): void
    {
        self::assertNull((new CandidateKeyMatch())->of(['id' => null], ['id']));
    }

    public function testOfAnswersNothingForAKeyMadeOfNoColumns(): void
    {
        self::assertNull((new CandidateKeyMatch())->of(['id' => 1], []));
    }

    public function testCarriedByReportsARowRepeatingEveryValue(): void
    {
        self::assertTrue((new CandidateKeyMatch())->carriedBy(['id' => 1], ['id' => 1, 'name' => 'a']));
    }

    public function testCarriedByReportsARowThatDiffersInOneOfThem(): void
    {
        self::assertFalse((new CandidateKeyMatch())->carriedBy(['id' => 1, 'tenant' => 'a'], ['id' => 1]));
    }
}
