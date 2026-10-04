<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionVersion::class)]
#[Medium]
final class ExtensionVersionTest extends TestCase
{
    public function testOptionOfTheOldVersion(): void
    {
        self::assertSame('old_version', (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionVersion(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\VersionRole::Source, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('1.0')))->option());
    }

    public function testRenderKeepsAWordVersion(): void
    {
        self::assertSame('CREATE EXTENSION e VERSION v1', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE EXTENSION e VERSION v1')->toString());
    }
}
