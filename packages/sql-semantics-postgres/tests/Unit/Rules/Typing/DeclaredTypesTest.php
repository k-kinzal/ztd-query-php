<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\DeclaredTypes;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\NamedOnPath;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredDomain;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(DeclaredTypes::class)]
#[Small]
final class DeclaredTypesTest extends TestCase
{
    public function testFactKnowsACatalogType(): void
    {
        $type = new ArrayOf(Builtin::Int4);
        $fact = (new DeclaredTypes())->fact($type);
        self::assertInstanceOf(Known::class, $fact);
        self::assertSame($type, $fact->descriptor);
    }

    public function testFactDependsOnTheMissingInputsOfATypeKnownByNameOnly(): void
    {
        $missing = new UndeclaredDomain(new QualifiedName(new Name('mood')));
        $fact = (new DeclaredTypes())->fact(new ArrayOf(new NamedOnPath(new QualifiedName(new Name('mood')), [$missing])));
        self::assertInstanceOf(Dependent::class, $fact);
        self::assertSame([$missing], $fact->missing);
    }
}
