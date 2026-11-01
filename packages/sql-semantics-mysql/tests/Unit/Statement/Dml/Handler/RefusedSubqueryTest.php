<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedSubquery;

#[CoversClass(RefusedSubquery::class)]
#[Small]
final class RefusedSubqueryTest extends TestCase
{
    public function testMessageRetainsOnlyTheInputPosition(): void
    {
        $problem = new RefusedSubquery(25);

        self::assertSame('A HANDLER read cannot contain a subquery.', $problem->message());
        self::assertSame(['offset' => 25], get_object_vars($problem));
    }
}
