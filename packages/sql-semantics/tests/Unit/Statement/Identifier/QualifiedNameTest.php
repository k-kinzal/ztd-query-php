<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Identifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(QualifiedName::class)]
#[Small]
final class QualifiedNameTest extends TestCase
{
    public function testPartsAreKeptInTheirRoles(): void
    {
        $name = new QualifiedName(new Name('users'), new Name('app'), new Name('db'));

        self::assertSame('users', $name->name->value);
        self::assertSame('app', $name->schema?->value);
        self::assertSame('db', $name->catalog?->value);
    }

    public function testQualifiersAreOptional(): void
    {
        $name = new QualifiedName(new Name('users'));

        self::assertNull($name->schema);
        self::assertNull($name->catalog);
    }

    public function testACatalogRequiresASchema(): void
    {
        $this->expectExceptionMessage('A catalog qualifier requires a schema qualifier.');

        new QualifiedName(new Name('users'), null, new Name('db'));
    }
}
