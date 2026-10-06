<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\ForeignKeys::class)]
#[Medium]
final class ForeignKeysTest extends TestCase
{
    public function testActionsRefusesTwoClausesForOneEvent(): void
    {
        $this->expectExceptionMessage('A foreign key has at most one ON UPDATE and one ON DELETE clause.');
        (new \SqlSemantics\Platform\PostgreSql\Rules\Table\ForeignKeys())->actions([new \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferentialAction(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceEvent::Delete, \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceAction::Cascade), new \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferentialAction(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceEvent::Delete, \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceAction::Restrict)]);
    }

    public function testDeriveResolvesTheReferencedTable(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE TABLE n (x int REFERENCES t (zz) MATCH PARTIAL)', $context);
        self::assertSame([
          0 => 'column "zz" referenced in foreign key constraint does not exist',
          1 => 'MATCH PARTIAL not yet implemented',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testWriteTargetWritesTheReferencedSide(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (x int REFERENCES t (a) MATCH FULL ON UPDATE SET DEFAULT ON DELETE NO ACTION)', []);
        self::assertSame('CREATE TABLE n (x INT REFERENCES t (a) MATCH FULL ON UPDATE SET DEFAULT ON DELETE NO ACTION)', $statement->toString());
    }

    public function testDeriveReportsAReferencedView(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE VIEW v AS SELECT 1 AS a')];
        self::assertSame(['referenced relation "v" is not a table'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('CREATE TABLE c (a int REFERENCES v (zz))', $context)->facts->diagnostics));
    }
}
