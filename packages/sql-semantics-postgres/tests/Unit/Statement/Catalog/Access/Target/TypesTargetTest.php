<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\TypesTarget::class)]
#[Medium]
final class TypesTargetTest extends TestCase
{
    public function testObject(): void
    {
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Type, (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\TypesTarget(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Type, [new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')])]))->object());
    }

    public function testDeriveTargetResolvesNothing(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('GRANT USAGE ON TYPE app.money TO joe', [])->facts->diagnostics));
    }

    public function testRenderWritesTheKindAndTheNames(): void
    {
        self::assertSame('GRANT usage ON DOMAIN app.email, d TO joe', (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('GRANT usage ON DOMAIN app.email, d TO joe')->toString());
    }

    public function testRejectsAnotherKind(): void
    {
        $this->expectExceptionMessage('Dotted names are types or domains.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\TypesTarget(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Schema, [new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')])]);
    }
}
