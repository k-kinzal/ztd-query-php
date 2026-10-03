<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Routine\Routines;
use SqlSemantics\Platform\PostgreSql\Platform;

#[CoversClass(Routines::class)]
#[Small]
final class RoutinesTest extends TestCase
{
    public function testStatementIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("COMMENT ON TABLE t IS 'x'");
        $this->expectExceptionMessage('No semantic rule is implemented for: CommentStmt: COMMENT ON object_type_any_name any_name IS comment_text');
        $lowering->routines->statement($tree->find('CommentStmt')[0]);
    }

    public function testFunctionSignatureIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP FUNCTION f(int), g');
        $this->expectExceptionMessage('No semantic rule is implemented for: function_with_argtypes: func_name func_args');
        $lowering->routines->functionSignature($tree->find('function_with_argtypes')[0]);
    }

    public function testFunctionSignaturesIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP FUNCTION f(int), g');
        $this->expectExceptionMessage('No semantic rule is implemented for: function_with_argtypes_list: function_with_argtypes_list , function_with_argtypes');
        $lowering->routines->functionSignatures($tree->find('function_with_argtypes_list')[0]);
    }

    public function testAggregateSignatureIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP AGGREGATE a(int), b(*)');
        $this->expectExceptionMessage('No semantic rule is implemented for: aggregate_with_argtypes: func_name aggr_args');
        $lowering->routines->aggregateSignature($tree->find('aggregate_with_argtypes')[0]);
    }

    public function testAggregateSignaturesIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP AGGREGATE a(int), b(*)');
        $this->expectExceptionMessage('No semantic rule is implemented for: aggregate_with_argtypes_list: aggregate_with_argtypes_list , aggregate_with_argtypes');
        $lowering->routines->aggregateSignatures($tree->find('aggregate_with_argtypes_list')[0]);
    }

    public function testOperatorSignatureIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP OPERATOR +(int, int), -(int, int)');
        $this->expectExceptionMessage('No semantic rule is implemented for: operator_with_argtypes: any_operator oper_argtypes');
        $lowering->routines->operatorSignature($tree->find('operator_with_argtypes')[0]);
    }

    public function testOperatorSignaturesIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP OPERATOR +(int, int), -(int, int)');
        $this->expectExceptionMessage('No semantic rule is implemented for: operator_with_argtypes_list: operator_with_argtypes_list , operator_with_argtypes');
        $lowering->routines->operatorSignatures($tree->find('operator_with_argtypes_list')[0]);
    }

    public function testObjectKindIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("COMMENT ON TABLE t IS 'x'");
        $this->expectExceptionMessage('No semantic rule is implemented for: object_type_any_name: TABLE');
        $lowering->routines->objectKind($tree->find('object_type_any_name')[0]);
    }

    public function testOperatorDefinitionsIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER OPERATOR + (int, int) SET (RESTRICT = NONE)');
        $this->expectExceptionMessage('No semantic rule is implemented for: operator_def_list: operator_def_elem');
        $lowering->routines->operatorDefinitions($tree->find('operator_def_list')[0]);
    }
}
