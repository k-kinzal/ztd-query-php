<?php

declare(strict_types=1);

namespace Tests\Unit\Reflection\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Reflection\Key\PrimaryColumns::class)]
final class PrimaryColumnsTest extends TestCase
{
    public function testColumnsKeepsCatalogOrderAndSkipsInvalidMetadata(): void
    {
        $statement = new \Tests\Fake\FakeStatement([['column_name' => 'tenant_id'], ['column_name' => null], ['column_name' => 'id']]);
        self::assertSame(['"tenant_id"', '"id"'], (new \ZtdQuery\Platform\Postgres\Reflection\Key\PrimaryColumns())->columns($statement));
        self::assertSame([], (new \ZtdQuery\Platform\Postgres\Reflection\Key\PrimaryColumns())->columns(false));
    }
}
