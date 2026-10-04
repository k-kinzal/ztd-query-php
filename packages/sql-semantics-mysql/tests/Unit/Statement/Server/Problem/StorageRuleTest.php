<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageRule;

#[CoversClass(StorageRule::class)]
#[Small]
final class StorageRuleTest extends TestCase
{
    public function testCasesHoldTheServerErrors(): void
    {
        self::assertSame(['ER_FILEGROUP_OPTION_ONLY_ONCE', 'ER_WRONG_SIZE_NUMBER', 'ER_SIZE_OVERFLOW_ERROR'], array_column(StorageRule::cases(), 'value'));
    }
}
