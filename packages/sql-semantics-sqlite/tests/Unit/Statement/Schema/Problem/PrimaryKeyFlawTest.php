<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyFlaw;

#[CoversClass(PrimaryKeyFlaw::class)]
#[Small]
final class PrimaryKeyFlawTest extends TestCase
{
    public function testCasesDescribeEachFlaw(): void
    {
        self::assertSame(['Repeated', 'MissingWithoutRowid', 'ExpressionTerm', 'AutoincrementNotIntegerKey', 'AutoincrementWithoutRowid'], array_column(PrimaryKeyFlaw::cases(), 'name'));
        self::assertSame('The table has more than one primary key.', PrimaryKeyFlaw::Repeated->value);
    }
}
