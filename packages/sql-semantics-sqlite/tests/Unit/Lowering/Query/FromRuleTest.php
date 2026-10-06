<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Query\FromRule;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\DerivedQuery;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinKeyword;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOn;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinStep;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinUsing;
use SqlSemantics\Platform\Sqlite\Statement\Relation\NestedInput;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableCall;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(FromRule::class)]
#[Medium]
final class FromRuleTest extends TestCase
{
    public function testFromIsNullWithoutTheClause(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $bare = $semantics->analyze('SELECT 1')->statement;
        $read = $semantics->analyze('SELECT 1 FROM t')->statement;

        self::assertInstanceOf(Select::class, $bare);
        self::assertInstanceOf(Select::class, $read);
        self::assertNull($bare->from);
        self::assertInstanceOf(TableInput::class, $read->from);
        self::assertSame('t', $read->from->name->name->value);
    }

    public function testTermsAnswersOneTermWithoutAConstraintAndAChainOtherwise(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $single = $semantics->analyze('SELECT * FROM t')->statement;
        $pair = $semantics->analyze('SELECT * FROM t, u')->statement;
        $leading = $semantics->analyze('SELECT * FROM t ON 1');

        self::assertInstanceOf(Select::class, $single);
        self::assertInstanceOf(Select::class, $pair);
        self::assertInstanceOf(TableInput::class, $single->from);
        self::assertInstanceOf(JoinChain::class, $pair->from);
        self::assertInstanceOf(TableInput::class, $pair->from->first);
        self::assertCount(1, $pair->from->steps);
        self::assertTrue($pair->from->steps[0]->operator->comma);
        self::assertNull($pair->from->constraint);
        self::assertInstanceOf(Select::class, $leading->statement);
        self::assertInstanceOf(JoinChain::class, $leading->statement->from);
        self::assertSame([], $leading->statement->from->steps);
        self::assertInstanceOf(JoinOn::class, $leading->statement->from->constraint);
        self::assertInstanceOf(Misuse::class, $leading->facts->diagnostics[0]);
        self::assertSame(MisuseRule::OnWithoutJoin, $leading->facts->diagnostics[0]->rule);
        self::assertSame('SELECT * FROM t ON 1', $leading->toString());
    }

    public function testTermLowersEachKindOfTermWithItsAlias(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("SELECT * FROM t AS x, main.u INDEXED BY i, json_each('[1]') AS j, (SELECT 1) AS d, (t JOIN u) AS n");

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(JoinChain::class, $operation->statement->from);
        $first = $operation->statement->from->first;
        self::assertInstanceOf(TableInput::class, $first);
        self::assertSame('x', $first->alias?->value);
        self::assertNull($first->index);
        $relations = array_map(static fn (JoinStep $step): object => $step->relation, $operation->statement->from->steps);
        self::assertInstanceOf(TableInput::class, $relations[0]);
        self::assertSame('main', $relations[0]->name->schema?->value);
        self::assertSame('i', $relations[0]->index?->index?->value);
        self::assertInstanceOf(TableCall::class, $relations[1]);
        self::assertSame('json_each', $relations[1]->name->name->value);
        self::assertCount(1, $relations[1]->arguments);
        self::assertSame('j', $relations[1]->alias?->value);
        self::assertInstanceOf(DerivedQuery::class, $relations[2]);
        self::assertInstanceOf(Select::class, $relations[2]->query);
        self::assertSame('d', $relations[2]->alias?->value);
        self::assertInstanceOf(NestedInput::class, $relations[3]);
        self::assertInstanceOf(JoinChain::class, $relations[3]->relation);
        self::assertSame('n', $relations[3]->alias?->value);
        self::assertSame("SELECT * FROM t AS x, main.u INDEXED BY i, json_each('[1]') AS j, (SELECT 1) AS d, (t JOIN u) AS n", $operation->toString());
    }

    public function testOperatorLowersTheCommaTheBareJoinAndTheKeywordJoins(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT * FROM t, u JOIN v NATURAL LEFT OUTER JOIN w CROSS JOIN x INNER JOIN y RIGHT JOIN z FULL OUTER JOIN q');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(JoinChain::class, $operation->statement->from);
        self::assertSame([[true, []], [false, []], [false, [JoinKeyword::Natural, JoinKeyword::Left, JoinKeyword::Outer]], [false, [JoinKeyword::Cross]], [false, [JoinKeyword::Inner]], [false, [JoinKeyword::Right]], [false, [JoinKeyword::Full, JoinKeyword::Outer]]], array_map(static fn (JoinStep $step): array => [$step->operator->comma, $step->operator->words], $operation->statement->from->steps));
        self::assertSame('SELECT * FROM t, u JOIN v NATURAL LEFT OUTER JOIN w CROSS JOIN x INNER JOIN y RIGHT JOIN z FULL OUTER JOIN q', $operation->toString());
    }

    public function testWordLowersAWordBeforeJoinAsAKeywordWhenWrittenAsOneAndAsANameOtherwise(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT * FROM t LEFT bogus JOIN u');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(JoinChain::class, $operation->statement->from);
        $words = $operation->statement->from->steps[0]->operator->words;
        self::assertCount(2, $words);
        self::assertSame(JoinKeyword::Left, $words[0]);
        self::assertInstanceOf(Name::class, $words[1]);
        self::assertSame('bogus', $words[1]->value);
        self::assertInstanceOf(Misuse::class, $operation->facts->diagnostics[0]);
        self::assertSame(MisuseRule::UnknownJoinType, $operation->facts->diagnostics[0]->rule);
        self::assertSame('SELECT * FROM t LEFT bogus JOIN u', $operation->toString());
    }

    public function testConstraintLowersOnUsingAndNone(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT * FROM t JOIN u ON t.a = u.a JOIN v USING (a, b) JOIN w');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(JoinChain::class, $operation->statement->from);
        $constraints = array_map(static fn (JoinStep $step): ?object => $step->constraint, $operation->statement->from->steps);
        self::assertInstanceOf(JoinOn::class, $constraints[0]);
        self::assertInstanceOf(JoinUsing::class, $constraints[1]);
        self::assertSame(['a', 'b'], array_map(static fn (Name $column): string => $column->value, $constraints[1]->columns));
        self::assertNull($constraints[2]);
        self::assertSame('SELECT * FROM t JOIN u ON t.a = u.a JOIN v USING (a, b) JOIN w', $operation->toString());
    }

    public function testIndexedLowersIndexedByAndNotIndexed(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT * FROM t INDEXED BY i, u NOT INDEXED');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(JoinChain::class, $operation->statement->from);
        $first = $operation->statement->from->first;
        $second = $operation->statement->from->steps[0]->relation;
        self::assertInstanceOf(TableInput::class, $first);
        self::assertInstanceOf(TableInput::class, $second);
        self::assertSame('i', $first->index?->index?->value);
        self::assertNotNull($second->index);
        self::assertNull($second->index->index);
        self::assertSame('SELECT * FROM t INDEXED BY i, u NOT INDEXED', $operation->toString());
    }

    public function testOptionalIndexedIsNullWithoutAClause(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $bare = $semantics->analyze('DELETE FROM t')->statement;
        $chosen = $semantics->analyze('DELETE FROM t INDEXED BY i')->statement;
        $refused = $semantics->analyze('DELETE FROM t NOT INDEXED')->statement;

        self::assertInstanceOf(Delete::class, $bare);
        self::assertInstanceOf(Delete::class, $chosen);
        self::assertInstanceOf(Delete::class, $refused);
        self::assertNull($bare->target->index);
        self::assertSame('i', $chosen->target->index?->index?->value);
        self::assertNotNull($refused->target->index);
        self::assertNull($refused->target->index->index);
    }
}
