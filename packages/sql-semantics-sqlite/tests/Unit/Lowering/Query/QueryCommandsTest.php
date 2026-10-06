<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Lowering\Query\QueryCommands;
use SqlSemantics\Platform\Sqlite\Platform;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger;

#[CoversClass(QueryCommands::class)]
#[Medium]
final class QueryCommandsTest extends TestCase
{
    public function testCommandLowersEveryFormOfASelectCommand(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertInstanceOf(Select::class, $semantics->analyze('SELECT 1')->statement);
        self::assertInstanceOf(ValuesClause::class, $semantics->analyze('VALUES (1)')->statement);
        self::assertInstanceOf(Compound::class, $semantics->analyze('SELECT 1 UNION SELECT 2')->statement);
        self::assertInstanceOf(WithQuery::class, $semantics->analyze('WITH c AS (SELECT 1) SELECT * FROM c')->statement);
    }

    public function testCommandLowersACreateTriggerCommand(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT 1; END');

        self::assertInstanceOf(CreateTrigger::class, $operation->statement);
        self::assertSame('CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT 1; END', $operation->toString());
    }

    public function testCommandPassesDataChangesToTheMutationRules(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertInstanceOf(InsertRows::class, $semantics->analyze('INSERT INTO t VALUES (1)')->statement);
        self::assertInstanceOf(Update::class, $semantics->analyze('UPDATE t SET a = 1')->statement);
        self::assertInstanceOf(Delete::class, $semantics->analyze('DELETE FROM t')->statement);
    }

    public function testCommandAnswersNullForACommandOfAnotherFamily(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves());
        $tree = $platform->parser($profile)->parse('CREATE TABLE t (a); SELECT 1');
        $list = $tree->children[0];

        self::assertInstanceOf(Node::class, $list);
        $commands = (new Lists())->items($list);
        $definition = $commands[0]->children[0];
        $query = $commands[1]->children[0];
        self::assertInstanceOf(Node::class, $definition);
        self::assertInstanceOf(Node::class, $query);
        $lowering->trivia->index($commands[1]);
        self::assertNull($lowering->queryCommands->command($lowering->productions->form($lowering->unwrapped($definition))));
        self::assertInstanceOf(Select::class, $lowering->queryCommands->command($lowering->productions->form($lowering->unwrapped($query))));
    }
}
