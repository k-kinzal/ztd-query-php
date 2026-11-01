<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedLogSubquery;

#[CoversClass(RefusedLogSubquery::class)]
#[Small]
final class RefusedLogSubqueryTest extends TestCase
{
    public function testMessageRetainsOnlyTheInputPosition(): void
    {
        $problem = new RefusedLogSubquery(25);

        self::assertSame('A PURGE moment cannot contain a subquery.', $problem->message());
        self::assertSame(['offset' => 25], get_object_vars($problem));
    }
}
