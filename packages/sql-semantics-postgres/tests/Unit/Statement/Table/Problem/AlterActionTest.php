<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\AlterAction;

#[CoversClass(AlterAction::class)]
#[Small]
final class AlterActionTest extends TestCase
{
    public function testCasesHoldTheServerNamesOfTheActions(): void
    {
        self::assertSame(['ALTER COLUMN ... SET DEFAULT', 'ALTER COLUMN ... SET'], [AlterAction::SetDefault->value, AlterAction::SetColumnAttributes->value]);
    }
}
