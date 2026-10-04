<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\RenameEnumLabel::class)]
#[Medium]
final class RenameEnumLabelTest extends TestCase
{
    public function testRenderWritesRename(): void
    {
        self::assertSame('ALTER TYPE mood RENAME VALUE \'sad\' TO \'blue\'', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE mood RENAME VALUE \'sad\' TO \'blue\'')->toString());
    }

    public function testDeriveStatementAcceptsAShortLabel(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE mood RENAME VALUE \'sad\' TO \'blue\'')->facts->diagnostics);
    }
}
