<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\TypeDeclaration;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(BuiltinIdentity::class)]
#[Medium]
final class BuiltinIdentityTest extends TestCase
{
    public function testNameIsTheCanonicalBackingValue(): void
    {
        self::assertSame('double precision', BuiltinIdentity::DoublePrecision->name());
        self::assertSame(BuiltinIdentity::DoublePrecision, BuiltinIdentity::from('double precision'));
        self::assertSame('double precision', TypeDeclaration::write(TypeDescriptor::builtin(Dialect::PostgreSql, 'double precision'))->toString());
    }

    public function testIncludesInferredResultIdentitiesBesideStorageTypes(): void
    {
        self::assertContains('unknown', array_column(BuiltinIdentity::cases(), 'value'));
        self::assertContains('dynamic', array_column(BuiltinIdentity::cases(), 'value'));
        self::assertContains('never', array_column(BuiltinIdentity::cases(), 'value'));
        self::assertNotContains('nope', array_column(BuiltinIdentity::cases(), 'value'));
    }

    public function testBindsAParameterlessColumnTypeToTheEnumCase(): void
    {
        $type = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a BOOLEAN)')->tables[0]->columns[0]->type;
        self::assertSame(BuiltinIdentity::Boolean, $type->identity);
        self::assertSame('boolean', $type->name);
    }
}
