<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Descriptor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Missing\UndeclaredDomain;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\NamedOnPath;

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
