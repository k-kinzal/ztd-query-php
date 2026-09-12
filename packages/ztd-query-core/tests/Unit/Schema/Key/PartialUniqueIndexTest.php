<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\Key\PartialUniqueIndex;

#[CoversClass(PartialUniqueIndex::class)]
final class PartialUniqueIndexTest extends TestCase
{
    public function testRetainsStructuredIndexMetadata(): void
    {
        $index = new PartialUniqueIndex('users_active_email', ['email'], "status = 'active'");

        self::assertSame('users_active_email', $index->name);
        self::assertSame(['email'], $index->columns);
        self::assertSame("status = 'active'", $index->predicate);
    }

}
