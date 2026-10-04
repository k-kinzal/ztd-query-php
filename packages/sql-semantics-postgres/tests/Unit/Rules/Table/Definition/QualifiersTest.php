<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Qualifiers::class)]
#[Medium]
final class QualifiersTest extends TestCase
{
    public function testCheckedRefusesATableConstraint(): void
    {
        $this->expectExceptionMessage('A column qualifier is a column constraint, a deferral attribute or a collation.');
        (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Qualifiers())->checked([new \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\IndexConstraint(true, new \SqlSemantics\Statement\Identifier\Name('i'))]);
    }

    public function testDeriveReportsSeveralCollations(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (a text COLLATE "C" COLLATE "POSIX")', []);
        self::assertSame([
          0 => 'multiple COLLATE clauses not allowed',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testAttributesReportsMisplacedAndRepeatedAttributes(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (a int DEFERRABLE, b int UNIQUE DEFERRABLE NOT DEFERRABLE INITIALLY DEFERRED INITIALLY IMMEDIATE)', []);
        self::assertSame([
          0 => 'misplaced DEFERRABLE clause',
          1 => 'multiple DEFERRABLE/NOT DEFERRABLE clauses not allowed',
          2 => 'constraint declared INITIALLY DEFERRED must be DEFERRABLE',
          3 => 'multiple INITIALLY IMMEDIATE/DEFERRED clauses not allowed',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testConflictsReportsCombinedValueSources(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (a int DEFAULT 1 GENERATED ALWAYS AS (2) STORED GENERATED ALWAYS AS IDENTITY GENERATED ALWAYS AS (3) STORED)', []);
        self::assertSame([
          0 => 'multiple generation clauses specified for column "a"',
          1 => 'both default and identity specified for column "a"',
          2 => 'both default and generation expression specified for column "a"',
          3 => 'both identity and generation expression specified for column "a"',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }
}
