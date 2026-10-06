<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionOption::class)]
#[Small]
final class ExtensionOptionTest extends TestCase
{
    public function testOptionOfTheSchemaOption(): void
    {
        self::assertSame('schema', (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionSchema(new \SqlSemantics\Statement\Identifier\Name('ext')))->option());
    }
}
