<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Invariant;
use SqlSemantics\Statement\Declaration\TypeDeclaration;
use SqlSemantics\Statement\Declaration\TypeDescriptor;

#[CoversClass(TypeDeclaration::class)]
#[CoversClass(TypeDescriptor::class)]
#[CoversClass(Builtin::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeName::class)]
#[CoversClass(TypeDeclaration::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[CoversClass(Invariant::class)]
#[CoversClass(Invariant::class)]
#[Small]
final class TypeDeclarationTest extends TestCase
{
    public function testSeparatesTypeFactsFromImpliedColumnFacts(): void
    {
        $type = new TypeDescriptor(Builtin::Integer);
        $declaration = new TypeDeclaration($type, autoIncrement: true, notNull: true);
        self::assertSame($type, $declaration->type);
        self::assertTrue($declaration->autoIncrement);
        self::assertTrue($declaration->notNull);
        self::assertFalse($declaration->unique);
        self::assertFalse((new TypeDeclaration($type))->autoIncrement);
    }
}
