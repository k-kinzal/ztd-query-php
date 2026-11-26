<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\AlterComposite::class)]
#[Medium]
final class AlterCompositeTest extends TestCase
{
    public function testRenderWritesTheChanges(): void
    {
        self::assertSame('ALTER TYPE pair ADD ATTRIBUTE c text, DROP ATTRIBUTE a', (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('ALTER TYPE pair ADD ATTRIBUTE c text, DROP ATTRIBUTE a')->toString());
    }

    public function testDeriveStatementDerivesTheChanges(): void
    {
        self::assertSame([], (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('ALTER TYPE pair ADD ATTRIBUTE c text, DROP ATTRIBUTE a')->facts->diagnostics);
    }
}
