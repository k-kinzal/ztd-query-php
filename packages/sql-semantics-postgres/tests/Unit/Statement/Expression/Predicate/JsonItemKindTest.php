<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\JsonItemKind;

#[CoversClass(JsonItemKind::class)]
#[Small]
final class JsonItemKindTest extends TestCase
{
    public function testValuesAreTheWrittenKinds(): void
    {
        self::assertSame('JSON SCALAR', JsonItemKind::JsonScalar->value);
    }
}
