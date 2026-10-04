<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\DropEnumLabel::class)]
#[Medium]
final class DropEnumLabelTest extends TestCase
{
    public function testRenderWritesDropValue(): void
    {
        self::assertSame('ALTER TYPE mood DROP VALUE \'sad\'', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE mood DROP VALUE \'sad\'')->toString());
    }

    public function testDeriveStatementReportsTheRejection(): void
    {
        self::assertSame('dropping an enum value is not implemented', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE mood DROP VALUE \'sad\'')->facts->diagnostics[0]->message());
    }
}
