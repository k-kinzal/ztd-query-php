<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Table\Tables;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;

#[CoversClass(Tables::class)]
#[Small]
final class TablesTest extends TestCase
{
    public function testStatementIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE INDEX i ON t (a) WHERE a > 0');
        $this->expectExceptionMessage('No semantic rule is implemented for: IndexStmt: CREATE opt_unique INDEX opt_concurrently opt_single_name ON relation_expr access_method_clause ( index_params )');
        $lowering->tables->statement($tree->find('IndexStmt')[0]);
    }

    public function testColumnQualifiersIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int NOT NULL)');
        $this->expectExceptionMessage('No semantic rule is implemented for: ColQualList: ColQualList ColConstraint');
        $lowering->tables->columnQualifiers($tree->find('ColQualList')[0]);
    }

    public function testTableConstraintIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int, CHECK (a > 0) NOT DEFERRABLE)');
        $this->expectExceptionMessage('No semantic rule is implemented for: TableConstraint: ConstraintElem');
        $lowering->tables->tableConstraint($tree->find('TableConstraint')[0]);
    }

    public function testConstraintAttributesIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int, CHECK (a > 0) NOT DEFERRABLE)');
        $this->expectExceptionMessage('No semantic rule is implemented for: ConstraintAttributeSpec: ConstraintAttributeSpec ConstraintAttributeElem');
        $lowering->tables->constraintAttributes($tree->find('ConstraintAttributeSpec')[0]);
    }

    public function testIndexParametersIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE INDEX i ON t (a) WHERE a > 0');
        $this->expectExceptionMessage('No semantic rule is implemented for: index_params: index_elem');
        $lowering->tables->indexParameters($tree->find('index_params')[0]);
    }

    public function testUniqueNullTreatmentIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE UNIQUE INDEX i ON t (a) INCLUDE (b) NULLS DISTINCT');
        $this->expectExceptionMessage('No semantic rule is implemented for: opt_unique_null_treatment: NULLS_P DISTINCT');
        $lowering->tables->uniqueNullTreatment($tree->find('opt_unique_null_treatment')[0]);
    }

    public function testColumnDefaultIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER TABLE t ALTER a SET DEFAULT 1');
        $this->expectExceptionMessage('No semantic rule is implemented for: alter_column_default: SET DEFAULT a_expr');
        $lowering->tables->columnDefault($tree->find('alter_column_default')[0]);
    }

    public function testPredicateIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int, EXCLUDE (a WITH =) WHERE (a > 0))');
        $this->expectExceptionMessage('No semantic rule is implemented for: OptWhereClause: WHERE ( a_expr )');
        $lowering->tables->predicate($tree->find('OptWhereClause')[0]);
    }

    public function testPersistenceIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE TEMP TABLE t (a int)');
        $this->expectExceptionMessage('No semantic rule is implemented for: OptTemp: TEMP');
        $lowering->tables->persistence($tree->find('OptTemp')[0]);
    }

    public function testCreateTableAsExecuteIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE TEMP TABLE t AS EXECUTE p (1) WITH NO DATA');
        $this->expectExceptionMessage('No semantic rule is implemented for: ExecuteStmt: CREATE OptTemp TABLE create_as_target AS EXECUTE name execute_param_clause opt_with_data');
        $lowering->tables->createTableAsExecute($lowering->productions->form($tree->find('ExecuteStmt')[0]), new Select([]));
    }
}
