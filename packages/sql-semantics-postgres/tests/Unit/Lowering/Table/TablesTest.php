<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Table\Tables::class)]
#[Medium]
final class TablesTest extends TestCase
{
    public function testStatementLowersAnIndex(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE INDEX i ON t (a)');
        $value = $lowering->tables->statement($tree->find('IndexStmt')[0]);
        self::assertSame('SqlSemantics\\Platform\\PostgreSql\\Statement\\Table\\Index\\CreateIndex', get_debug_type($value));
    }

    public function testOtherLowersATrigger(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TRIGGER g AFTER INSERT ON t EXECUTE FUNCTION f()');
        $value = $lowering->tables->other($tree->find('CreateTrigStmt')[0]);
        self::assertSame('SqlSemantics\\Platform\\PostgreSql\\Statement\\Table\\Trigger\\CreateTrigger', get_debug_type($value));
    }

    public function testColumnQualifiersLowersTheQualifiersInOrder(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int NOT NULL COLLATE "C" DEFERRABLE)');
        $value = $lowering->tables->columnQualifiers($tree->find('ColQualList')[0]);
        self::assertSame([
          0 => 'SqlSemantics\\Platform\\PostgreSql\\Statement\\Constraint\\Column\\NotNull',
          1 => 'SqlSemantics\\Platform\\PostgreSql\\Statement\\Constraint\\ColumnCollation',
          2 => 'SqlSemantics\\Platform\\PostgreSql\\Statement\\Constraint\\ConstraintAttribute',
        ], array_map(static fn ($qualifier): string => $qualifier::class, $value));
    }

    public function testTableConstraintLowersANamedConstraint(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int, CONSTRAINT c CHECK (a > 0) NOT DEFERRABLE)');
        $value = $lowering->tables->tableConstraint($tree->find('TableConstraint')[0]);
        $n1 = $value;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TableCheck::class, $n1);
        self::assertSame('c', $n1->name?->value);
    }

    public function testConstraintAttributesLowersTheAttributesInOrder(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int, CHECK (a > 0) NOT VALID NO INHERIT)');
        $value = $lowering->tables->constraintAttributes($tree->find('ConstraintAttributeSpec')[0]);
        self::assertSame([
          0 =>
          \SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute::NotValid,
          1 =>
          \SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute::NoInherit,
        ], $value);
    }

    public function testIndexParametersLowersTheKeys(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE INDEX i ON t (a, (b + 1))');
        $value = $lowering->tables->indexParameters($tree->find('index_params')[0]);
        self::assertSame(2, count($value));
    }

    public function testUniqueNullTreatmentIsTrueForNullsDistinct(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE UNIQUE INDEX i ON t (a) INCLUDE (b) NULLS DISTINCT');
        $value = $lowering->tables->uniqueNullTreatment($tree->find('opt_unique_null_treatment')[0]);
        self::assertSame(true, $value);
    }

    public function testUniqueNullTreatmentIsFalseForNullsNotDistinct(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE UNIQUE INDEX i ON t (a) NULLS NOT DISTINCT');
        $value = $lowering->tables->uniqueNullTreatment($tree->find('opt_unique_null_treatment')[0]);
        self::assertSame(false, $value);
    }

    public function testUniqueNullTreatmentIsNullWhenNothingIsWritten(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE UNIQUE INDEX i ON t (a)');
        $value = $lowering->tables->uniqueNullTreatment($tree->find('opt_unique_null_treatment')[0]);
        self::assertSame(null, $value);
    }

    public function testColumnDefaultIsNullForDropDefault(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER TABLE t ALTER a DROP DEFAULT');
        $value = $lowering->tables->columnDefault($tree->find('alter_column_default')[0]);
        self::assertSame(null, $value);
    }

    public function testPredicateLowersTheCondition(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int, EXCLUDE (a WITH =) WHERE (a > 0))');
        $value = $lowering->tables->predicate($tree->find('OptWhereClause')[0]);
        self::assertSame('SqlSemantics\\Platform\\PostgreSql\\Statement\\Expression\\BinaryOperation', get_debug_type($value));
    }

    public function testPersistenceIsNullForAPermanentTable(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int)');
        $value = $lowering->tables->persistence($tree->find('OptTemp')[0]);
        self::assertSame(null, $value);
    }

    public function testCreateTableAsExecuteBuildsTheStatement(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t AS EXECUTE q');
        $execute = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('EXECUTE q')->statement;
        $value = $lowering->tables->createTableAsExecute($lowering->productions->form($tree->find('ExecuteStmt')[0]), $execute);
        $n1 = $value;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTableAsExecute::class, $n1);
        self::assertSame('t', $n1->target->name->name->value);
    }
}
