<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\TableIdentifier as Subject;

#[CoversClass(Subject::class)]
final class TableIdentifierTest extends TestCase
{
    public function testNormalizeQualifiedAndQuotedNames(): void
    {
        $normalizer = new Subject();
        self::assertSame('users', $normalizer->normalize('`App`.`USERS`'));
        self::assertSame('users', $normalizer->normalize('[USERS]'));
        self::assertSame('users', $normalizer->normalize('"USERS"'));
    }
}
