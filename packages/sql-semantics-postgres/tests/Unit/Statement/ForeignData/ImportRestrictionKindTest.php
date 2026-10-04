<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\ForeignData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\ForeignData\ImportRestrictionKind::class)]
#[Medium]
final class ImportRestrictionKindTest extends TestCase
{
    public function testLimitToIsTwoKeywords(): void
    {
        self::assertSame('IMPORT FOREIGN SCHEMA r LIMIT TO (a) FROM SERVER s INTO l', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('IMPORT FOREIGN SCHEMA r LIMIT TO (a) FROM SERVER s INTO l')->toString());
    }
}
