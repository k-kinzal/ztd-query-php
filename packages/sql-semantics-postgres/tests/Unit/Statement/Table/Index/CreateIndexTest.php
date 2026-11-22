<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex::class)]
#[Medium]
final class CreateIndexTest extends TestCase
{
    public function testCreatedSchemaIsTheSchemaOfTheTable(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('CREATE INDEX ON s.t (a)', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex::class, $n1);
        self::assertSame('s', $n1->createdSchema()?->value);
    }

    public function testDeriveStatementResolvesTheTable(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('CREATE INDEX i ON t (a) WHERE zz', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex::class, $n1);
        $n2 = $statement->facts->relation($n1)->table;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable::class, $n2);
        self::assertSame([
          0 => 'Column zz does not exist.',
        ], $n2->table === $context[0] ? array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics) : null);
    }

    public function testDeriveElementLocatesTheTableInTheSchema(): void
    {
        $statement = (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('CREATE INDEX ON t (a)');
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\SchemaElement::class, $statement->statement);
        $derivation = new \SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation($statement->context);
        $statement->statement->deriveElement($derivation, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('s'));
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex::class, $n1);
        $n2 = $derivation->facts()->relation($n1)->table;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\UndeclaredTable::class, $n2);
        self::assertSame('s', $n2->missing->name->schema?->value);
    }

    public function testDeriveRelationResolvesTheTableAsWritten(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('CREATE INDEX ON t (a)', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex::class, $n1);
        self::assertSame(3, count($n1->deriveRelation(new \SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation($statement->context), new \SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment($statement->context))->shape->slots));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS i ON ONLY t USING btree (a) INCLUDE (b) NULLS NOT DISTINCT WITH (fillfactor = 70) TABLESPACE x WHERE a > 0', []);
        self::assertSame('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS i ON ONLY t USING btree (a) INCLUDE (b) NULLS NOT DISTINCT WITH (fillfactor = 70) TABLESPACE x WHERE a > 0', $statement->toString());
    }

    public function testRefusesIfNotExistsWithoutAName(): void
    {
        $this->expectExceptionMessage('IF NOT EXISTS needs an index name.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex(null, new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t'))), [new \SqlSemantics\Platform\PostgreSql\Statement\Table\Index\IndexElement(new \SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ColumnKey(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a')))], false, false, true);
    }
}
