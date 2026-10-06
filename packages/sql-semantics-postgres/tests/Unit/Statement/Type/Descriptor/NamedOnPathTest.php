<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Descriptor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\NamedOnPath;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredDomain;

#[CoversClass(NamedOnPath::class)]
#[Small]
final class NamedOnPathTest extends TestCase
{
    public function testNameJoinsTheWrittenParts(): void
    {
        $name = new QualifiedName(new Name('money'), new Name('app'));
        self::assertSame('app.money', (new NamedOnPath($name, [new UndeclaredDomain($name)]))->name());
    }

    public function testNameOfAnUnqualifiedTypeIsTheName(): void
    {
        $name = new QualifiedName(new Name('mood'));
        self::assertSame('mood', (new NamedOnPath($name, [new UndeclaredDomain($name)]))->name());
    }
}
