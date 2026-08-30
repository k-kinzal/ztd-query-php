<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\Key\CandidateKeyConflict;

#[CoversClass(CandidateKeyConflict::class)]
final class CandidateKeyConflictTest extends TestCase
{
    public function testCarriesConflictIdentity(): void
    {
        $conflict = new CandidateKeyConflict(3, 'users_email', ['email' => 'alice@example.com']);

        self::assertSame(3, $conflict->rowIndex);
        self::assertSame('users_email', $conflict->keyName);
        self::assertSame(['email' => 'alice@example.com'], $conflict->values);
    }
}
