<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\IdentifierQuoter as Subject;

#[CoversClass(Subject::class)]
final class IdentifierQuoterTest extends TestCase
{
    public function testQuoteTableNameEscapesEachQualifiedPart(): void
    {
        self::assertSame('`app`.`order``items`', (new Subject())->quoteTableName('app.order`items'));
        self::assertSame('`users`', (new Subject())->quoteTableName('users'));
    }
}
