<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\TypeSystem\Casts;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(Casts::class)]
#[Medium]
final class CastsTest extends TestCase
{
    #[TestWith(['CREATE CAST (integer AS text) WITH INOUT AS IMPLICIT', 'CREATE CAST(integer AS text) WITH INOUT AS IMPLICIT'])]
    #[TestWith(['CREATE CAST (integer AS text) WITH FUNCTION f', 'CREATE CAST(integer AS text) WITH FUNCTION "f"'])]
    #[TestWith(['DROP CAST (integer AS text)', 'DROP CAST(integer AS text)'])]
    #[TestWith(['CREATE TRANSFORM FOR integer LANGUAGE plperl (FROM SQL WITH FUNCTION f)', 'CREATE TRANSFORM FOR integer LANGUAGE "plperl"(FROM SQL WITH FUNCTION "f")'])]
    #[TestWith(['DROP TRANSFORM FOR integer LANGUAGE plperl CASCADE', 'DROP TRANSFORM FOR integer LANGUAGE "plperl" CASCADE'])]
    public function testWriteSpellsEachForm(string $sql, string $expected): void
    {
        self::assertSame($expected, Casts::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql))?->toString());
    }

    public function testWriteIgnoresOtherStatements(): void
    {
        self::assertNull(Casts::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    public function testSignatureWritesSourceAndTarget(): void
    {
        self::assertSame('(integer AS text)', Casts::signature(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), TypeDescriptor::builtin(Dialect::PostgreSql, 'text'))->toString());
    }

    public function testFunctionWritesOnlyAPresentFunction(): void
    {
        self::assertSame([], Casts::function('TO', null));
        self::assertSame('TO SQL WITH FUNCTION "f"', Casts::function('TO', new RoutineByName(new QualifiedName(['f'])))[0]->toString());
    }
}
