<?php

declare(strict_types=1);

namespace Tests\Unit\Reflection\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Reflection\Key\IndexDefinitions::class)]
final class IndexDefinitionsTest extends TestCase
{
    public function testKeepsSqlAndPartialMetadataSeparate(): void
    {
        $index = new \ZtdQuery\Schema\PartialUniqueIndex('active_email', ['email'], 'active');
        $definitions = new \ZtdQuery\Platform\Postgres\Reflection\Key\IndexDefinitions(['CONSTRAINT "unique_id" UNIQUE ("id")'], ['active_email' => $index]);
        self::assertSame(['CONSTRAINT "unique_id" UNIQUE ("id")'], $definitions->sql);
        self::assertSame(['active_email' => $index], $definitions->partialIndexes);
    }
}
