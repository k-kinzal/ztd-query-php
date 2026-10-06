<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageRule;

#[CoversClass(StorageProblem::class)]
#[Small]
final class StorageProblemTest extends TestCase
{
    public function testMessageDescribesEachRule(): void
    {
        self::assertSame('The size of MAX_SIZE is a number with an optional K, M or G multiplier (ER_WRONG_SIZE_NUMBER).', (new StorageProblem(StorageRule::WrongSize, 'MAX_SIZE'))->message());
    }
}
