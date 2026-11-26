<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\SetSchema;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(SetSchema::class)]
#[Medium]
final class SetSchemaTest extends TestCase
{
    public function testDeriveStatementResolvesAMovedTable(): void
    {
        $operation = (new Inspection())->diagnose('ALTER TABLE IF EXISTS t SET SCHEMA s', []);
        self::assertSame([], $operation->facts->diagnostics);
        $statement = $operation->statement;
        self::assertInstanceOf(SetSchema::class, $statement);
        self::assertNull($operation->facts->relation($statement->object)->table);
    }

    public function testRenderWritesTheSchema(): void
    {
        $operation = (new Inspection())->diagnose('ALTER OPERATOR CLASS c USING btree SET SCHEMA "S"');
        self::assertSame('ALTER OPERATOR CLASS c USING btree SET SCHEMA "S"', $operation->toString());
    }

    public function testRejectsAWrongForm(): void
    {
        $this->expectExceptionMessage('SET SCHEMA names the object as the grammar names objects of its kind.');
        new SetSchema(ObjectKind::Schema, new UnqualifiedName(new Name('s')), new Name('t'));
    }

    public function testRejectsIfExistsOfAnotherKind(): void
    {
        $this->expectExceptionMessage('SET SCHEMA accepts IF EXISTS for relations only.');
        new SetSchema(ObjectKind::Extension, new UnqualifiedName(new Name('e')), new Name('t'), true);
    }

    public function testDeriveStatementReportsAnotherKind(): void
    {
        $semantics = new Inspection();
        $context = [$semantics->diagnose('CREATE TABLE t (a int)')];
        self::assertSame(['"t" is not a foreign table'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('ALTER FOREIGN TABLE t SET SCHEMA x', $context)->facts->diagnostics));
        self::assertSame([], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('ALTER TABLE t SET SCHEMA x', $context)->facts->diagnostics));
    }
}
