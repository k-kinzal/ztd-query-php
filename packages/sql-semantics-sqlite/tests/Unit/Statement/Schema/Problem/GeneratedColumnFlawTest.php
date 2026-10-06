<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\GeneratedColumnFlaw;

#[CoversClass(GeneratedColumnFlaw::class)]
#[Small]
final class GeneratedColumnFlawTest extends TestCase
{
    public function testCasesDescribeEachFlaw(): void
    {
        self::assertSame(['WithDefault', 'InPrimaryKey', 'NoPlainColumn'], array_column(GeneratedColumnFlaw::cases(), 'name'));
        self::assertSame('A generated column cannot be part of the PRIMARY KEY.', GeneratedColumnFlaw::InPrimaryKey->value);
    }
}
