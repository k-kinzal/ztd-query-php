<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Identity\SetSequenceName::class)]
#[Medium]
final class SetSequenceNameTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (SEQUENCE NAME app.t_id_seq)', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Identity\AddColumnIdentity::class, $statement->actions[0]);
        self::assertEquals(new Relation\Identity\SetSequenceName(new QualifiedName(['app', 't_id_seq'])), $statement->actions[0]->options[0]);
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "id" ADD GENERATED ALWAYS AS IDENTITY(SEQUENCE NAME "app"."t_id_seq")', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAnOverQualifiedName(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Identity\SetSequenceName(new QualifiedName(['a', 'b', 'c', 'd']));
    }
}
