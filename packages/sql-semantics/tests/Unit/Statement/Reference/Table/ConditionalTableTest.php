<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(ConditionalTable::class)]
#[Medium]
final class ConditionalTableTest extends TestCase
{
    public function testCandidatesAreKeptWhileAnEarlierSchemaIsUnknown(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE main.t (a INTEGER)');
        $query = $semantics->analyze('SELECT a FROM t', $semantics->context([$table], false));

        $resolution = $query->facts->relation($query->singleNamedInput())->table;

        self::assertInstanceOf(ConditionalTable::class, $resolution);
        self::assertSame([$table->declarations()[0]], $resolution->candidates);
        self::assertSame('the declaration of relation t', $resolution->missing->describe());
        self::assertFalse($query->facts->relation($query->singleNamedInput())->shape->complete());
    }

    public function testCandidatesAreNotNeededWhenTheContextIsComplete(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE main.t (a INTEGER)');
        $query = $semantics->analyze('SELECT a FROM t', [$table]);

        self::assertInstanceOf(DeclaredTable::class, $query->facts->relation($query->singleNamedInput())->table);
    }

    public function testCandidatesAreAtLeastOne(): void
    {
        $this->expectExceptionMessage('A conditional table has at least one candidate.');

        new ConditionalTable([], new UndeclaredRelation(new QualifiedName(new Name('t'))));
    }
}
