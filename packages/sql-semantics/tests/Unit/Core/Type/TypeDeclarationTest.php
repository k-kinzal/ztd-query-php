<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Schema\Invariant;
use SqlSemantics\Core\Type\Builtin;
use SqlSemantics\Core\Type\TypeDeclaration;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;

#[CoversClass(TypeDeclaration::class)]
#[CoversClass(TypeDescriptor::class)]
#[CoversClass(Invariant::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[Small]
final class TypeDeclarationTest extends TestCase
{
    public function testSeparatesTypeFactsFromImpliedColumnFacts(): void
    {
        $type = new TypeDescriptor(PostgreSqlDialect::PostgreSql, Builtin::Integer);
        $declaration = new TypeDeclaration($type, autoIncrement: true, notNull: true);
        self::assertSame($type, $declaration->type);
        self::assertTrue($declaration->autoIncrement);
        self::assertTrue($declaration->notNull);
        self::assertFalse($declaration->unique);
        self::assertFalse((new TypeDeclaration($type))->autoIncrement);
    }
}
