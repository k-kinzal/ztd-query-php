<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Platform;
use SqlSemantics\Platform\Sqlite\Statement\Inspection\Explain;
use SqlSemantics\Platform\Sqlite\Statement\Inspection\ExplainMode;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Lowering::class)]
#[Medium]
final class LoweringTest extends TestCase
{
    public function testStatementsLowersEveryCommandInOrderAndSkipsEmptyOnes(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves());
        $statements = $lowering->statements($platform->parser($profile)->parse('SELECT a; ; DELETE FROM t;'));

        self::assertCount(2, $statements);
        self::assertInstanceOf(Select::class, $statements[0]);
        self::assertInstanceOf(Delete::class, $statements[1]);
    }

    public function testStatementsRecordsTheOperandLeavesItLowers(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $leaves = new Leaves();
        $lowering = new Lowering($platform->productions($profile), $leaves);
        $lowering->statements($platform->parser($profile)->parse('SELECT a FROM t'));

        self::assertSame($leaves, $lowering->leaves);
        self::assertSame(['a', 't'], array_map(static fn (object $leaf): string => $leaf instanceof Name ? $leaf->value : $leaf::class, $leaves->all()));
    }

    public function testTerminatedAnswersNullForAnEmptyCommand(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves());
        $tree = $platform->parser($profile)->parse('; SELECT 1');
        $list = $tree->children[0];

        self::assertInstanceOf(Node::class, $list);
        $commands = (new Lists())->items($list);
        self::assertCount(2, $commands);
        self::assertNull($lowering->terminated($commands[0]));
        self::assertInstanceOf(Select::class, $lowering->terminated($commands[1]));
    }

    public function testTerminatedWrapsAnExplainedCommand(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves());
        $tree = $platform->parser($profile)->parse('EXPLAIN QUERY PLAN SELECT 1');
        $list = $tree->children[0];

        self::assertInstanceOf(Node::class, $list);
        $explained = $lowering->terminated((new Lists())->items($list)[0]);
        self::assertInstanceOf(Explain::class, $explained);
        self::assertSame(ExplainMode::QueryPlan, $explained->mode);
        self::assertInstanceOf(Select::class, $explained->statement);
    }

    public function testUnwrappedAnswersTheCommandInsideTheGrammarWrapper(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves());
        $tree = $platform->parser($profile)->parse('SELECT 1');
        $list = $tree->children[0];

        self::assertInstanceOf(Node::class, $list);
        $wrapper = (new Lists())->items($list)[0]->children[0];
        self::assertInstanceOf(Node::class, $wrapper);
        self::assertSame('cmdx', $wrapper->name);
        $command = $lowering->unwrapped($wrapper);
        self::assertSame('cmd', $command->name);
    }

    public function testCommandLowersAQueryCommandAndADefinitionCommand(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves());
        $tree = $platform->parser($profile)->parse('SELECT 1; CREATE TABLE t (a)');
        $list = $tree->children[0];

        self::assertInstanceOf(Node::class, $list);
        $commands = (new Lists())->items($list);
        $first = $commands[0]->children[0];
        $second = $commands[1]->children[0];
        self::assertInstanceOf(Node::class, $first);
        self::assertInstanceOf(Node::class, $second);
        self::assertInstanceOf(Select::class, $lowering->command($lowering->unwrapped($first)));
        self::assertInstanceOf(CreateTable::class, $lowering->command($lowering->unwrapped($second)));
    }

    public function testCommandUsesTheRuleObjectsOfTheSameLowering(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $productions = $platform->productions($profile);
        $lowering = new Lowering($productions, new Leaves());

        self::assertSame($productions, $lowering->productions);
        self::assertContains('ecmd: cmdx SEMI', $productions->all());
    }
}
