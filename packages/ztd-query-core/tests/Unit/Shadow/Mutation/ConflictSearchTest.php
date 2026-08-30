<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\Key\CandidateKeyConflict;
use ZtdQuery\Schema\Key\CandidateKeyMatch;
use ZtdQuery\Schema\Key\CandidateKeySet;
use ZtdQuery\Shadow\Mutation\ConflictSearch;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertOperator;
use ZtdQuery\Shadow\Mutation\UpsertColumnSource;
use ZtdQuery\Shadow\Mutation\UpsertExpression;
use ZtdQuery\Shadow\Mutation\UpsertExpressionKind;

#[CoversClass(ConflictSearch::class)]
#[UsesClass(CandidateKeySet::class)]
#[UsesClass(CandidateKeyMatch::class)]
#[UsesClass(CandidateKeyConflict::class)]
#[UsesClass(UpsertExpression::class)]
#[UsesClass(\ZtdQuery\Shadow\Mutation\Upsert\UpsertColumn::class)]
#[UsesClass(\ZtdQuery\Shadow\Mutation\Upsert\UpsertComparison::class)]
#[UsesClass(\ZtdQuery\Shadow\Mutation\Upsert\UpsertNumber::class)]
#[UsesClass(\ZtdQuery\Shadow\Mutation\Upsert\UpsertTruth::class)]
#[UsesClass(UpsertOperator::class)]
final class ConflictSearchTest extends TestCase
{
    public function testOfAnswersTheRowTheIncomingRowWouldCollideWith(): void
    {
        $search = new ConflictSearch(CandidateKeySet::fromSchema(['id']), null, 'users');

        self::assertSame(1, $search->of(['id' => 2], [['id' => 1], ['id' => 2]])?->rowIndex);
    }

    public function testOfIsNothingWhereNoRowAlreadyThereCarriesTheSameKey(): void
    {
        $search = new ConflictSearch(CandidateKeySet::fromSchema(['id']), null, 'users');

        self::assertNull($search->of(['id' => 3], [['id' => 1], ['id' => 2]]));
    }

    public function testOfIsNothingWhereTheIncomingRowTakesNoPartInTheNarrowedKey(): void
    {
        $search = new ConflictSearch(
            CandidateKeySet::fromSchema(['id']),
            UpsertExpression::binary(
                UpsertExpressionKind::Equal,
                UpsertExpression::column(UpsertColumnSource::Existing, 'active'),
                UpsertExpression::literal(true),
            ),
            'users',
        );

        self::assertNull($search->of(['id' => 1, 'active' => false], [['id' => 1, 'active' => true]]));
    }

    public function testOfLeavesOutTheRowsAlreadyThereThatTakeNoPart(): void
    {
        $search = new ConflictSearch(
            CandidateKeySet::fromSchema(['id']),
            UpsertExpression::binary(
                UpsertExpressionKind::Equal,
                UpsertExpression::column(UpsertColumnSource::Existing, 'active'),
                UpsertExpression::literal(true),
            ),
            'users',
        );

        self::assertNull($search->of(['id' => 1, 'active' => true], [['id' => 1, 'active' => false]]));
    }

    public function testOfReportsTheRowUnderTheKeyTheCallerKnowsItBy(): void
    {
        $search = new ConflictSearch(
            CandidateKeySet::fromSchema(['id']),
            UpsertExpression::binary(
                UpsertExpressionKind::Equal,
                UpsertExpression::column(UpsertColumnSource::Existing, 'active'),
                UpsertExpression::literal(true),
            ),
            'users',
        );

        $conflict = $search->of(
            ['id' => 2, 'active' => true],
            [['id' => 1, 'active' => false], ['id' => 2, 'active' => true]],
        );

        self::assertSame(1, $conflict?->rowIndex);
    }
}
