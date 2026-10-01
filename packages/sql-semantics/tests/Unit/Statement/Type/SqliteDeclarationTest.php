<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Affinity;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeName;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Type\SqliteDeclaration;

#[CoversClass(SqliteDeclaration::class)]
#[Small]
final class SqliteDeclarationTest extends TestCase
{
    #[TestWith([null, ''])]
    #[TestWith(['', '""'])]
    #[TestWith(['INTEGER', 'INTEGER'])]
    #[TestWith(['VARCHAR(10)', '"VARCHAR(10)"'])]
    #[TestWith(['A /*INT*/ B', '"A /*INT*/ B"'])]
    #[TestWith(['a"b', '"a""b"'])]
    public function testToStringPreservesTheDeclaredTypeName(?string $name, string $expected): void
    {
        $type = new SqliteDeclaration($name);
        self::assertSame($name, $type->name);
        self::assertSame($expected, $type->toString());
        self::assertTrue((new SemanticGraph())->containsOnlyValues($type));
    }

    #[TestWith(['FLOATING POINT', Affinity::Integer])]
    #[TestWith(['A /*INT*/ B', Affinity::Integer])]
    #[TestWith(['INTBLOB', Affinity::Integer])]
    #[TestWith(['BLOBTEXT', Affinity::Text])]
    #[TestWith(['BLOBREAL', Affinity::Blob])]
    #[TestWith(['DOUBLE', Affinity::Real])]
    #[TestWith(['STRING', Affinity::Numeric])]
    #[TestWith(['', Affinity::Blob])]
    public function testAffinityUsesTheDatabaseTypeNameAndDocumentedPrecedence(string $name, Affinity $expected): void
    {
        $type = new SqliteDeclaration($name);
        self::assertSame($expected, $type->affinity($name));
        self::assertSame($expected, $type->descriptor->affinity);
    }

    public function testAffinityDescribesStrictAnyWithoutCoercingStoredValues(): void
    {
        $ordinary = new SqliteDeclaration('ANY');
        $strict = new SqliteDeclaration('ANY', strict: true);
        self::assertSame(Builtin::Any, $ordinary->descriptor->name);
        self::assertSame(Affinity::Numeric, $ordinary->descriptor->affinity);
        self::assertSame(Builtin::Any, $strict->descriptor->name);
        self::assertSame(Affinity::Blob, $strict->descriptor->affinity);
    }

    public function testAffinityDistinguishesOmittedAndArbitraryDeclaredNames(): void
    {
        self::assertSame(Builtin::Dynamic, (new SqliteDeclaration())->descriptor->name);
        $declared = new SqliteDeclaration('VARCHAR(12)');
        self::assertInstanceOf(TypeName::class, $declared->descriptor->name);
        self::assertSame(['VARCHAR(12)'], $declared->descriptor->name->parts);
        self::assertNull($declared->descriptor->length);
    }

    public function testPermitsRowidAliasDistinguishesCustomNamesakes(): void
    {
        $type = new SqliteDeclaration('INTEGER', custom: true);
        self::assertSame('INTEGER', $type->name);
        self::assertFalse($type->native);
        self::assertFalse($type->permitsRowidAlias());
        self::assertSame('"INTEGER"(0)', $type->toString());
    }

    #[TestWith(['INTEGER', true])]
    #[TestWith(['integer', true])]
    #[TestWith(['INTEGER(1)', false])]
    #[TestWith(['INT', false])]
    #[TestWith(['A /*INTEGER*/ B', false])]
    #[TestWith([null, false])]
    public function testPermitsRowidAliasRequiresTheExactDecodedTypeName(?string $name, bool $expected): void
    {
        self::assertSame($expected, (new SqliteDeclaration($name))->permitsRowidAlias());
    }
}
