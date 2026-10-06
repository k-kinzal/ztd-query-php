<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Hint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintScope;

#[CoversClass(IndexHintScope::class)]
#[Small]
final class IndexHintScopeTest extends TestCase
{
    public function testCasesNameTheThreeScopes(): void
    {
        self::assertSame(['Join', 'OrderBy', 'GroupBy'], array_column(IndexHintScope::cases(), 'name'));
    }
}
