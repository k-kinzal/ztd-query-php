<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\SchemaObjectKind;

#[CoversClass(SchemaObjectKind::class)]
#[Small]
final class SchemaObjectKindTest extends TestCase
{
    public function testCasesSpellTheKeywordOfEachObjectKind(): void
    {
        self::assertSame(['TABLE', 'VIEW', 'INDEX', 'TRIGGER'], array_column(SchemaObjectKind::cases(), 'value'));
    }
}
