<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Sequence;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Declaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Sequence::class)]
#[Medium]
final class SequenceTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerItemsAnswersTheMembersInSourceOrder(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerItemsAnswersTheMembersInSourceOrder')]
    public function testItemsAnswersTheMembersInSourceOrder(string $release): void
    {
        $create = (new Semantics(Dialect::MySql, $release))->analyze('create procedure p(a int, b int, c int) begin declare x int; declare y, z int; set @a = 1; set @b = 2; set @c = 3; end');
        $statement = $create->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertSame(['a', 'b', 'c'], array_map(static fn (Parameter $parameter): string => $parameter->name->value, $statement->parameters->parameters));
        $block = $statement->body;
        self::assertInstanceOf(Block::class, $block);
        self::assertSame([VariableDeclaration::class, VariableDeclaration::class], array_map(static fn (Declaration $declaration): string => $declaration::class, $block->declarations));
        $second = $block->declarations[1];
        self::assertInstanceOf(VariableDeclaration::class, $second);
        self::assertSame(['y', 'z'], array_map(static fn (Name $name): string => $name->value, $second->names));
        self::assertCount(3, $block->statements);
        self::assertContainsOnlyInstancesOf(SetVariables::class, $block->statements);
        self::assertSame('CREATE PROCEDURE p(a INT, b INT, c INT) BEGIN DECLARE x INT; DECLARE y, z INT; SET @a = 1; SET @b = 2; SET @c = 3; END', $create->toString());
    }

    public function testItemsAnswersNothingForAnEmptyList(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $sequence = new Sequence(new Lowering($platform->productions($profile), new Leaves(), $profile));

        self::assertSame([], $sequence->items(new Node('sp_decls', 0, []), ['sp_decls:', 'sp_decls: sp_decls sp_decl ;']));
    }

    public function testItemsAnswersTheNonterminalMembersOfTheSpine(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $sequence = new Sequence(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $first = new Node('sp_decl', 0, []);
        $second = new Node('sp_decl', 1, []);
        $list = new Node('sp_decls', 1, [new Node('sp_decls', 1, [new Node('sp_decls', 0, []), $first]), $second]);

        self::assertSame([$first, $second], $sequence->items($list, ['sp_decls:', 'sp_decls: sp_decls sp_decl ;']));
    }

    public function testItemsRefusesASpineNodeOfAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $sequence = new Sequence(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: sp_decls: sp_decls sp_decl ;');

        $sequence->items(new Node('sp_decls', 1, []), ['sp_decls:']);
    }
}
