<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Extensibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Identity;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Extensibility\Sequences;

#[CoversClass(Sequences::class)]
#[Medium]
final class SequencesTest extends TestCase
{
    #[TestWith(['CREATE TEMP SEQUENCE s INCREMENT 2 START 4', 'CREATE TEMPORARY SEQUENCE "s" INCREMENT BY 2 START WITH 4'])]
    #[TestWith(['ALTER SEQUENCE s RESTART 3 MAXVALUE 9', 'ALTER SEQUENCE "s" RESTART WITH 3 MAXVALUE 9'])]
    public function testWriteSpellsCanonicalKeywords(string $sql, string $expected): void
    {
        self::assertSame($expected, Sequences::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql))?->toString());
    }

    public function testWriteIgnoresOtherStatements(): void
    {
        self::assertNull(Sequences::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    public function testOptionWritesEachOptionForm(): void
    {
        self::assertSame('NO MAXVALUE', Sequences::option(Identity\SequenceFlag::NoMaxValue)->toString());
        self::assertSame('OWNED BY "t"."c"', Sequences::option(new Identity\SetSequenceOwner(new QualifiedName(['t', 'c'])))->toString());
        self::assertSame('RESTART', Sequences::option(new Identity\RestartIdentity(null))->toString());
        self::assertSame('AS smallint', Sequences::option(new Identity\SequenceStorage(\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'smallint')))->toString());
    }
}
