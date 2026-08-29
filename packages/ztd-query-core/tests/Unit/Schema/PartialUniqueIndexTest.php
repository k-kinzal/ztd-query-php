<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\PartialUniqueIndex;

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

    /**
     * @param list<string> $columns
     */
    #[TestWith(['', ['email'], "status = 'active'"])]
    #[TestWith([' ', ['email'], "status = 'active'"])]
    #[TestWith(['users_active_email', [], "status = 'active'"])]
    #[TestWith(['users_active_email', ['email'], ' '])]
    public function testRejectsIncompleteIndexMetadata(string $name, array $columns, string $predicate): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PartialUniqueIndex($name, $columns, $predicate);
    }
}
