<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\OperatorIdentity;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\TypeSystem\Operators;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(Operators::class)]
#[Medium]
final class OperatorsTest extends TestCase
{
    #[TestWith(['CREATE OPERATOR === (function = f, rightarg = integer)', 'CREATE OPERATOR === (FUNCTION = "f", RIGHTARG = integer)'])]
    #[TestWith(['ALTER OPERATOR === (integer, integer) SET (hashes)', 'ALTER OPERATOR === (integer, integer) SET (HASHES = TRUE)'])]
    #[TestWith(['CREATE OPERATOR CLASS c FOR TYPE integer USING btree AS OPERATOR 1 <', 'CREATE OPERATOR CLASS "c" FOR TYPE integer USING "btree" AS OPERATOR 1 <'])]
    #[TestWith(['ALTER OPERATOR FAMILY f USING btree ADD FUNCTION 1 g', 'ALTER OPERATOR FAMILY "f" USING "btree" ADD FUNCTION 1 "g"'])]
    #[TestWith(['ALTER OPERATOR FAMILY f USING btree DROP FUNCTION 1 (text)', 'ALTER OPERATOR FAMILY "f" USING "btree" DROP FUNCTION 1(text, text)'])]
    #[TestWith(['DROP OPERATOR + (integer, integer)', 'DROP OPERATOR + (integer, integer)'])]
    #[TestWith(['DROP OPERATOR CLASS c USING btree', 'DROP OPERATOR CLASS "c" USING "btree"'])]
    #[TestWith(['CREATE OPERATOR FAMILY f USING btree', 'CREATE OPERATOR FAMILY "f" USING "btree"'])]
    public function testWriteSpellsEachForm(string $sql, string $expected): void
    {
        self::assertSame($expected, Operators::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql))?->toString());
    }

    public function testWriteIgnoresOtherStatements(): void
    {
        self::assertNull(Operators::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    public function testSignatureSpellsAMissingOperandAsNone(): void
    {
        self::assertSame('"s".- (NONE, integer)', Operators::signature(new OperatorIdentity(new QualifiedName(['s', '-']), null, TypeDescriptor::builtin(Dialect::PostgreSql, 'integer')))->toString());
    }
}
