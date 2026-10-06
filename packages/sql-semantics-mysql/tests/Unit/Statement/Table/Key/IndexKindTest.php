<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;

#[CoversClass(IndexKind::class)]
#[Small]
final class IndexKindTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame('PRIMARY KEY', IndexKind::Primary->value);
        self::assertSame('FULLTEXT', IndexKind::FullText->value);
    }
}
