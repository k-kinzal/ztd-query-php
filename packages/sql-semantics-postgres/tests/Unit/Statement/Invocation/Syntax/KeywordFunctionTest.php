<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\KeywordFunction;

#[CoversClass(KeywordFunction::class)]
#[Small]
final class KeywordFunctionTest extends TestCase
{
    public function testCatalogOnlyTellsTheLegacyJsonObject(): void
    {
        self::assertTrue(KeywordFunction::JsonObject->catalogOnly());
        self::assertFalse(KeywordFunction::Overlay->catalogOnly());
    }
}
