<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\CandidateKeyConflict;
use ZtdQuery\Schema\CandidateKeySet;

#[CoversClass(CandidateKeySet::class)]
#[UsesClass(CandidateKeyConflict::class)]
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

    public function testEmptyKeySetNeverTreatsEqualRowsAsConflicting(): void
    {
        $keys = CandidateKeySet::fromSchema([]);
        $row = ['name' => 'same'];

        self::assertNull($keys->findConflict($row, [$row]));
    }
}
