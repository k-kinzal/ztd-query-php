<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyFlaw;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyProblem;

#[CoversClass(PrimaryKeyProblem::class)]
#[Small]
final class PrimaryKeyProblemTest extends TestCase
{
    public function testMessageDescribesTheFlaw(): void
    {
        self::assertSame('A WITHOUT ROWID table needs a primary key.', (new PrimaryKeyProblem(PrimaryKeyFlaw::MissingWithoutRowid))->message());
    }
}
