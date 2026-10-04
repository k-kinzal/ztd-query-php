<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\RelationsTarget::class)]
#[Medium]
final class RelationsTargetTest extends TestCase
{
    public function testObject(): void
    {
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Sequence, (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\RelationsTarget(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Sequence, [new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('s')))]))->object());
    }

    public function testDeriveTargetResolvesADeclaredTable(): void
    {
        $semantics = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql));
        $table = $semantics->analyze('CREATE TABLE items (id int4, price int4)');
        $operation = $semantics->analyze('GRANT SELECT (id) ON items TO joe', [$table]);
        $target = $operation->statement instanceof \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Grant ? $operation->statement->target : null;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\RelationsTarget::class, $target);
        $resolution = $operation->facts->relation($target->relations[0])->table;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $resolution);
        self::assertSame($table->declarations()[0], $resolution->table);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveTargetReportsAColumnTheTableLacks(): void
    {
        self::assertSame(['Column items.cost does not exist.'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT SELECT (cost), update (price) ON items TO joe', [(new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE items (id int4, price int4)')])->facts->diagnostics));
    }

    public function testDeriveTargetKeepsANameThatMayBeASequenceOpen(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT SELECT ON counters TO joe', [])->facts->diagnostics));
    }

    public function testDeriveTargetDoesNotResolveSequences(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT USAGE ON SEQUENCE counters TO joe', []);
        $target = $operation->statement instanceof \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Grant ? $operation->statement->target : null;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\RelationsTarget::class, $target);
        self::assertFalse($operation->facts->covers($target->relations[0]));
    }

    public function testRenderAlwaysWritesTable(): void
    {
        self::assertSame('GRANT SELECT ON TABLE s.t, "U" TO joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT SELECT ON s.t, "U" TO joe')->toString());
    }

    public function testRenderWritesSequence(): void
    {
        self::assertSame('GRANT usage ON SEQUENCE q TO joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT usage ON SEQUENCE q TO joe')->toString());
    }

    public function testRejectsOnly(): void
    {
        $this->expectExceptionMessage('A grant names a relation without ONLY.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\RelationsTarget(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Relation, [new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), true)]);
    }

    public function testRejectsAnotherKind(): void
    {
        $this->expectExceptionMessage('Qualified names are relations or sequences.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\RelationsTarget(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Schema, [new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')))]);
    }
}
