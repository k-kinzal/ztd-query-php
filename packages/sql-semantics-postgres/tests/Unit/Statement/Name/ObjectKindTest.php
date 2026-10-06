<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;

#[CoversClass(ObjectKind::class)]
#[Small]
final class ObjectKindTest extends TestCase
{
    public function testKeywordsSpellsTheKindWordByWord(): void
    {
        self::assertSame(['TEXT', 'SEARCH', 'PARSER'], ObjectKind::TextSearchParser->keywords());
        self::assertSame(['TABLE'], ObjectKind::Table->keywords());
    }
}
