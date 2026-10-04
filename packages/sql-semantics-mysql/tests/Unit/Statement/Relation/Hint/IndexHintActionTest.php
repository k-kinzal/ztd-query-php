<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Hint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintAction;

#[CoversClass(IndexHintAction::class)]
#[Small]
final class IndexHintActionTest extends TestCase
{
    public function testCasesSpellTheKeywords(): void
    {
        self::assertSame(['USE', 'FORCE', 'IGNORE'], array_column(IndexHintAction::cases(), 'value'));
    }
}
