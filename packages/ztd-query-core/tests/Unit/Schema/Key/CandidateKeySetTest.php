<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\Key\CandidateKeyConflict;
use ZtdQuery\Schema\Key\CandidateKeySet;

#[CoversClass(CandidateKeySet::class)]
#[UsesClass(CandidateKeyConflict::class)]
#[UsesClass(\ZtdQuery\Schema\Key\CandidateKeyMatch::class)]
final class CandidateKeySetTest extends TestCase
{
    public function testFindsConflictAcrossPrimaryAndUniqueCandidateKeys(): void
    {
        $keys = CandidateKeySet::fromSchema(['id'], ['users_email' => ['email']]);
        $rows = [
            ['id' => 1, 'email' => 'alice@example.com'],
            ['id' => 2, 'email' => 'bob@example.com'],
        ];

        $primaryConflict = $keys->findConflict(['id' => 1, 'email' => 'new@example.com'], $rows);
        $uniqueConflict = $keys->findConflict(['id' => 3, 'email' => 'bob@example.com'], $rows);

        self::assertInstanceOf(CandidateKeyConflict::class, $primaryConflict);
        self::assertSame('PRIMARY', $primaryConflict->keyName);
        self::assertSame(0, $primaryConflict->rowIndex);
        self::assertInstanceOf(CandidateKeyConflict::class, $uniqueConflict);
        self::assertSame('users_email', $uniqueConflict->keyName);
        self::assertSame(1, $uniqueConflict->rowIndex);
    }

    public function testNullDoesNotConflictOnCompositeUniqueKey(): void
    {
        $keys = CandidateKeySet::fromSchema([], ['tenant_slug' => ['tenant_id', 'slug']]);
        $rows = [['tenant_id' => 1, 'slug' => null]];

        self::assertNull($keys->findConflict(['tenant_id' => 1, 'slug' => null], $rows));
    }

    public function testIncompletePrimaryKeyDoesNotPreventUniqueKeyConflictDetection(): void
    {
        $keys = CandidateKeySet::fromSchema(['id'], ['users_email' => ['email']]);
        $rows = [['id' => 1, 'email' => 'alice@example.com']];

        $conflict = $keys->findConflict(['id' => null, 'email' => 'alice@example.com'], $rows);

        self::assertInstanceOf(CandidateKeyConflict::class, $conflict);
        self::assertSame('users_email', $conflict->keyName);
    }

    public function testMissingCandidateKeyValueDoesNotConflictWithStoredNull(): void
    {
        $keys = CandidateKeySet::fromSchema([], ['tenant_slug' => ['tenant_id', 'slug']]);
        $rows = [['tenant_id' => 1, 'slug' => null]];

        self::assertNull($keys->findConflict(['tenant_id' => 1], $rows));
    }

    public function testMissingStoredKeyValueDoesNotConflict(): void
    {
        $keys = CandidateKeySet::fromSchema([], ['users_email' => ['email']]);

        self::assertNull($keys->findConflict(['email' => 'alice@example.com'], [['id' => 1]]));
    }

    public function testEmptyKeySetNeverTreatsEqualRowsAsConflicting(): void
    {
        $keys = CandidateKeySet::fromSchema([]);
        $row = ['name' => 'same'];

        self::assertNull($keys->findConflict($row, [$row]));
    }

    public function testFromSchemaPutsThePrimaryKeyBeforeEveryUniqueOne(): void
    {
        $keys = CandidateKeySet::fromSchema(['id'], ['email' => ['email']]);

        self::assertSame(['PRIMARY', 'email'], array_keys($keys->keys()));
    }

    public function testFromSchemaNamesOnlyTheUniqueKeysWhereThereIsNoPrimaryOne(): void
    {
        $keys = CandidateKeySet::fromSchema([], ['email' => ['email']]);

        self::assertSame(['email' => ['email']], $keys->keys());
    }

    public function testKeysAnswersTheColumnsOfEveryKeyItWasBuiltFrom(): void
    {
        $keys = new CandidateKeySet(['PRIMARY' => ['id']]);

        self::assertSame(['PRIMARY' => ['id']], $keys->keys());
    }

    public function testFindConflictIsNothingWhereNoRowCarriesTheSameKey(): void
    {
        $keys = CandidateKeySet::fromSchema(['id']);

        self::assertNull($keys->findConflict(['id' => 2], [['id' => 1]]));
    }
}
