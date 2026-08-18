<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\ProductionPattern;
use SqlFaker\Sqlite\GenerationPlans;

#[CoversClass(GenerationPlans::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
final class GenerationPlansTest extends TestCase
{
    public function testMultiDmlPlanDirectsBothStatementOccurrences(): void
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

    public function testMultiDmlPlanRejectsAnUnknownFirstChoice(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GenerationPlans::multiDmlStatement(3, 0);
    }

    public function testMultiDmlPlanRejectsAnUnknownSecondChoice(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GenerationPlans::multiDmlStatement(0, 3);
    }

    public function testForeignKeyPlanRestrictsTheTableConstraintGrammar(): void
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

    public function testFunctionUpsertPlanRestrictsTheConflictFunctionGrammar(): void
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

    public function testTemporaryTablePlanRequiresTheTemporaryProduction(): void
    {
        $plan = GenerationPlans::temporaryTableStatement();

        self::assertSame('cmd', $plan->startRule());
        self::assertTrue($plan->patternAt('cmd', 0)?->matches(['create_table', 'create_table_args']) ?? false);
        self::assertTrue($plan->patternAt('temp', 0)?->matches(['TEMP']) ?? false);
    }

    public function testViewPlanRestrictsTheCreateGrammar(): void
    {
        $plan = GenerationPlans::viewStatement();

        self::assertSame('cmd', $plan->startRule());
        self::assertTrue($plan->patternAt('cmd', 0)?->matches(['createkw', 'VIEW', 'select']) ?? false);
        self::assertTrue($plan->patternAt('oneselect', 0)?->matches(['SELECT']) ?? false);
    }

    public function testGeneratedColumnPlanRequiresTheGeneratedConstraint(): void
    {
        $plan = GenerationPlans::generatedColumnStatement();

        self::assertSame('cmd', $plan->startRule());
        self::assertTrue($plan->patternAt('cmd', 0)?->matches(['create_table', 'create_table_args']) ?? false);
        self::assertTrue($plan->patternAt('create_table_args', 0)?->matches(['columnlist']) ?? false);
        self::assertTrue($plan->patternAt('carglist', 0)?->matches(['carglist', 'ccons']) ?? false);
        self::assertTrue($plan->patternAt('carglist', 1)?->matches([]) ?? false);
        self::assertTrue($plan->patternAt('ccons', 0)?->matches(['GENERATED', 'generated']) ?? false);
    }

    public function testForeignKeyCascadePlanRequiresBothCascadeActions(): void
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
}
