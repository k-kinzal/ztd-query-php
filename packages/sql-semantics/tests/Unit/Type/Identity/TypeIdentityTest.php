<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Identity\IntervalStorage;
use SqlSemantics\Type\Identity\Numeric\IntegerStorage;
use SqlSemantics\Type\Identity\SqliteDeclaration;
use SqlSemantics\Type\Identity\StorageAffinity;
use SqlSemantics\Type\Identity\TypeIdentity;

#[CoversClass(TypeIdentity::class)]
#[Medium]
final class TypeIdentityTest extends TestCase
{
    public function testNameIsTheCanonicalDiagnosticNameOfEveryImplementation(): void
    {
        $identities = [BuiltinIdentity::Text, new IntegerStorage(BuiltinIdentity::BigInt, unsigned: true), new IntervalStorage(), new SqliteDeclaration('VARCHAR', StorageAffinity::Text)];
        self::assertContainsOnlyInstancesOf(TypeIdentity::class, $identities);
        self::assertSame(['text', 'bigint unsigned', 'interval', 'VARCHAR'], array_map(static fn (TypeIdentity $identity): string => $identity->name(), $identities));
    }

    public function testDescriptorNameFollowsTheIdentityName(): void
    {
        $type = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a BIGINT UNSIGNED)')->tables[0]->columns[0]->type;
        self::assertSame($type->identity->name(), $type->name);
        self::assertSame('bigint unsigned', $type->name);
    }
}
