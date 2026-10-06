<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\ForeignData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\ImportRestriction::class)]
#[Medium]
final class ImportRestrictionTest extends TestCase
{
    public function testRenderKeepsOnly(): void
    {
        self::assertSame('IMPORT FOREIGN SCHEMA r EXCEPT (a, ONLY b) FROM SERVER s INTO l', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('IMPORT FOREIGN SCHEMA r EXCEPT (a, ONLY b) FROM SERVER s INTO l')->toString());
    }

    public function testRejectsAnEmptyList(): void
    {
        $this->expectExceptionMessage('An import restriction names at least one table.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\ImportRestriction(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\ImportRestrictionKind::Except, []);
    }
}
