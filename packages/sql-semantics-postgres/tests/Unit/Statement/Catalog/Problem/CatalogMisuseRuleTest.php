<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuseRule::class)]
#[Medium]
final class CatalogMisuseRuleTest extends TestCase
{
    public function testRuleOfARejectedEnumDrop(): void
    {
        $problem = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("ALTER TYPE t DROP VALUE 'a'")->facts->diagnostics[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuse::class, $problem);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuseRule::EnumValueDrop, $problem->rule);
    }
}
