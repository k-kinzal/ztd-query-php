<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Derivation\ProductionPattern;
use SqlFaker\Sqlite\GenerationPlans;

#[CoversClass(GenerationPlans::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
final class GenerationPlansTest extends TestCase
{
    public function testMultiDmlStatementDirectsBothStatementOccurrences(): void
    {
        $plan = GenerationPlans::multiDmlStatement(0, 2);

        self::assertSame('input', $plan->startRule());
        self::assertTrue($plan->patternAt('cmdlist', 0)?->matches(['cmdlist', 'ecmd']) ?? false);
        self::assertTrue($plan->patternAt('cmdlist', 1)?->matches(['ecmd']) ?? false);
        self::assertTrue($plan->patternAt('ecmd', 0)?->matches(['cmdx', 'SEMI']) ?? false);
        self::assertTrue($plan->patternAt('ecmd', 1)?->matches(['cmdx', 'SEMI']) ?? false);
        self::assertTrue($plan->patternAt('cmd', 0)?->matches(['insert_cmd']) ?? false);
        self::assertTrue($plan->patternAt('cmd', 1)?->matches(['DELETE']) ?? false);
        self::assertTrue($plan->patternAt('insert_cmd', 0)?->matches(['INSERT']) ?? false);
        self::assertTrue($plan->patternAt('insert_cmd', 1)?->matches(['INSERT']) ?? false);
        self::assertTrue($plan->patternAt('with', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('with', 1)?->matches([]) ?? false);
    }



    public function testFullTextSearchStatementRequiresMatchGrammar(): void
    {
        $plan = GenerationPlans::fullTextSearchStatement();

        self::assertSame('select', $plan->startRule());
        self::assertTrue($plan->patternAt('select', 0)?->matches(['selectnowith']) ?? false);
        self::assertTrue(
            $plan->patternAt('oneselect', 0)?->matches(['SELECT', 'selcollist', 'from', 'where_opt']) ?? false,
        );
        self::assertTrue($plan->patternAt('selcollist', 0)?->matches(['sclp', 'scanpt', 'STAR']) ?? false);
        self::assertTrue($plan->patternAt('sclp', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('from', 0)?->matches(['seltablist']) ?? false);
        self::assertTrue(
            $plan->patternAt('seltablist', 0)?->matches(['stl_prefix', 'nm', 'dbnm', 'as', 'on_using']) ?? false,
        );
        self::assertTrue($plan->patternAt('stl_prefix', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('on_using', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('where_opt', 0)?->matches(['expr']) ?? false);
        self::assertTrue($plan->patternAt('expr', 0)?->matches(['expr', 'likeop', 'expr']) ?? false);
        self::assertTrue($plan->patternAt('expr', 1)?->matches(['term']) ?? false);
        self::assertTrue($plan->patternAt('expr', 2)?->matches(['term']) ?? false);
        self::assertTrue($plan->patternAt('likeop', 0)?->matches(['MATCH']) ?? false);
    }

    public function testForeignKeyConstraintRestrictsTheTableConstraintGrammar(): void
    {
        $plan = GenerationPlans::foreignKeyConstraint();

        self::assertSame('conslist', $plan->startRule());
        self::assertTrue(
            $plan->patternAt('conslist', 0)?->matches(['conslist', 'tconscomma', 'tcons']) ?? false,
        );
        self::assertTrue($plan->patternAt('conslist', 1)?->matches(['tcons']) ?? false);
        self::assertTrue($plan->patternAt('tcons', 0)?->matches(['CONSTRAINT']) ?? false);
        self::assertTrue($plan->patternAt('tcons', 1)?->matches(['FOREIGN', 'KEY']) ?? false);
        self::assertTrue($plan->patternAt('tconscomma', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('eidlist_opt', 0)?->matches(['column']) ?? false);
    }

    public function testInsertFunctionUpsertStatementRestrictsTheConflictFunctionGrammar(): void
    {
        $plan = GenerationPlans::insertFunctionUpsertStatement();

        self::assertSame('cmd', $plan->startRule());
        self::assertTrue($plan->patternAt('cmd', 0)?->matches(['insert_cmd', 'select', 'upsert']) ?? false);
        self::assertTrue($plan->patternAt('with', 0)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('insert_cmd', 0)?->matches(['INSERT']) ?? false);
        self::assertTrue($plan->patternAt('select', 0)?->matches(['selectnowith']) ?? false);
        self::assertTrue($plan->patternAt('selectnowith', 0)?->matches(['oneselect']) ?? false);
        self::assertTrue($plan->patternAt('oneselect', 0)?->matches(['values']) ?? false);
        self::assertTrue(
            $plan->patternAt('values', 0)?->matches(['VALUES', 'LP', 'nexprlist', 'RP']) ?? false,
        );
        self::assertTrue($plan->patternAt('nexprlist', 0)?->matches(['expr']) ?? false);
        self::assertTrue(
            $plan->patternAt('upsert', 0)?->matches([
                'ON',
                'CONFLICT',
                'DO',
                'UPDATE',
                'SET',
                'setlist',
                'where_opt',
                'returning',
            ]) ?? false,
        );
        self::assertTrue($plan->patternAt('expr', 0)?->matches(['term']) ?? false);
        self::assertTrue($plan->patternAt('expr', 1)?->matches(['idj', 'LP', 'RP']) ?? false);
    }

    public function testTemporaryTableStatementRequiresTheTemporaryProduction(): void
    {
        $plan = GenerationPlans::temporaryTableStatement();

        self::assertSame('cmd', $plan->startRule());
        self::assertTrue($plan->patternAt('cmd', 0)?->matches(['create_table', 'create_table_args']) ?? false);
        self::assertTrue($plan->patternAt('temp', 0)?->matches(['TEMP']) ?? false);
    }

    public function testViewStatementRestrictsTheCreateGrammar(): void
    {
        $plan = GenerationPlans::viewStatement();

        self::assertSame('cmd', $plan->startRule());
        self::assertTrue($plan->patternAt('cmd', 0)?->matches(['createkw', 'VIEW', 'select']) ?? false);
        self::assertTrue($plan->patternAt('oneselect', 0)?->matches(['SELECT']) ?? false);
    }

    public function testGeneratedColumnStatementRequiresTheGeneratedConstraint(): void
    {
        $plan = GenerationPlans::generatedColumnStatement();

        self::assertSame('cmd', $plan->startRule());
        self::assertTrue($plan->patternAt('cmd', 0)?->matches(['create_table', 'create_table_args']) ?? false);
        self::assertTrue($plan->patternAt('create_table_args', 0)?->matches(['columnlist']) ?? false);
        self::assertTrue($plan->patternAt('carglist', 0)?->matches(['carglist', 'ccons']) ?? false);
        self::assertTrue($plan->patternAt('carglist', 1)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('ccons', 0)?->matches(['GENERATED', 'generated']) ?? false);
    }

    public function testForeignKeyCascadeStatementRequiresBothCascadeActions(): void
    {
        $plan = GenerationPlans::foreignKeyCascadeStatement();

        self::assertSame('cmd', $plan->startRule());
        self::assertTrue($plan->patternAt('cmd', 0)?->matches(['create_table', 'create_table_args']) ?? false);
        self::assertTrue(
            $plan->patternAt('create_table_args', 0)?->matches(['columnlist', 'conslist_opt']) ?? false,
        );
        self::assertTrue($plan->patternAt('conslist_opt', 0)?->matches(['tcons']) ?? false);
        self::assertTrue($plan->patternAt('tcons', 0)?->matches(['FOREIGN', 'KEY', 'REFERENCES']) ?? false);
        self::assertTrue($plan->patternAt('refargs', 0)?->matches(['refargs', 'refarg']) ?? false);
        self::assertTrue($plan->patternAt('refargs', 1)?->matches(['refargs', 'refarg']) ?? false);
        self::assertTrue($plan->patternAt('refargs', 2)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('refarg', 0)?->matches(['ON', 'DELETE']) ?? false);
        self::assertTrue($plan->patternAt('refarg', 1)?->matches(['ON', 'UPDATE']) ?? false);
        self::assertTrue($plan->patternAt('refact', 0)?->matches(['CASCADE']) ?? false);
        self::assertTrue($plan->patternAt('refact', 1)?->matches(['CASCADE']) ?? false);
    }

    public function testQuotedIdentifierPlansThatLexeme(): void
    {
        $plan = GenerationPlans::quotedIdentifier(1, 2);

        self::assertSame('quoted_identifier', $plan->lexicalTarget());
        self::assertSame(['minLength' => 1, 'maxLength' => 2], $plan->parameters());
    }

    public function testStringLiteralPlansThatLexeme(): void
    {
        $plan = GenerationPlans::stringLiteral(1, 2);

        self::assertSame('string_literal', $plan->lexicalTarget());
        self::assertSame(['minLength' => 1, 'maxLength' => 2], $plan->parameters());
    }

    public function testIntegerLiteralPlansThatLexeme(): void
    {
        $plan = GenerationPlans::integerLiteral(1, 2);

        self::assertSame('integer_literal', $plan->lexicalTarget());
        self::assertSame(['min' => 1, 'max' => 2], $plan->parameters());
    }

    public function testDecimalLiteralPlansThatLexeme(): void
    {
        $plan = GenerationPlans::decimalLiteral(1, 2);

        self::assertSame('decimal_literal', $plan->lexicalTarget());
        self::assertSame(['precision' => 1, 'scale' => 2], $plan->parameters());
    }

    public function testStatementBoundsTheWalkAtTheRuleItIsGrownFrom(): void
    {
        $plan = GenerationPlans::statement('select_stmt', 12);

        self::assertSame('select_stmt', $plan->startRule());
        self::assertSame(12, $plan->maxDepth());
    }
}
