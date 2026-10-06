<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinKeyword;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOperator;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(JoinOperator::class)]
#[Medium]
final class JoinOperatorTest extends TestCase
{
    public function testValidAcceptsTheCombinationsSqliteKnows(): void
    {
        self::assertTrue((new JoinOperator())->valid());
        self::assertTrue((new JoinOperator(true))->valid());
        self::assertTrue((new JoinOperator(false, [JoinKeyword::Left]))->valid());
        self::assertTrue((new JoinOperator(false, [JoinKeyword::Left, JoinKeyword::Outer]))->valid());
        self::assertTrue((new JoinOperator(false, [JoinKeyword::Natural, JoinKeyword::Left, JoinKeyword::Outer]))->valid());
        self::assertTrue((new JoinOperator(false, [JoinKeyword::Right, JoinKeyword::Outer]))->valid());
        self::assertTrue((new JoinOperator(false, [JoinKeyword::Full]))->valid());
        self::assertTrue((new JoinOperator(false, [JoinKeyword::Inner]))->valid());
        self::assertTrue((new JoinOperator(false, [JoinKeyword::Natural, JoinKeyword::Cross]))->valid());
    }

    public function testValidRejectsOuterWithoutASideAndInnerWithOuter(): void
    {
        self::assertFalse((new JoinOperator(false, [JoinKeyword::Outer]))->valid());
        self::assertFalse((new JoinOperator(false, [JoinKeyword::Inner, JoinKeyword::Left]))->valid());
        self::assertFalse((new JoinOperator(false, [JoinKeyword::Left, JoinKeyword::Cross]))->valid());
        self::assertFalse((new JoinOperator(false, [JoinKeyword::Left, new Name('WRONG')]))->valid());
        self::assertFalse((new JoinOperator(false, [JoinKeyword::Natural, JoinKeyword::Outer]))->valid());
    }

    public function testNaturalIsTrueOnlyForAValidNaturalJoin(): void
    {
        self::assertTrue((new JoinOperator(false, [JoinKeyword::Natural]))->natural());
        self::assertTrue((new JoinOperator(false, [JoinKeyword::Natural, JoinKeyword::Left]))->natural());
        self::assertFalse((new JoinOperator(false, [JoinKeyword::Natural, JoinKeyword::Outer]))->natural());
        self::assertFalse((new JoinOperator(false, [JoinKeyword::Left]))->natural());
        self::assertFalse((new JoinOperator(true))->natural());
    }

    public function testLeftIsTrueForLeftAndFullJoins(): void
    {
        self::assertTrue((new JoinOperator(false, [JoinKeyword::Left]))->left());
        self::assertTrue((new JoinOperator(false, [JoinKeyword::Full, JoinKeyword::Outer]))->left());
        self::assertFalse((new JoinOperator(false, [JoinKeyword::Right]))->left());
        self::assertFalse((new JoinOperator(false, [JoinKeyword::Left, JoinKeyword::Cross]))->left());
        self::assertFalse((new JoinOperator())->left());
    }

    public function testRightIsTrueForRightAndFullJoins(): void
    {
        self::assertTrue((new JoinOperator(false, [JoinKeyword::Right]))->right());
        self::assertTrue((new JoinOperator(false, [JoinKeyword::Full]))->right());
        self::assertFalse((new JoinOperator(false, [JoinKeyword::Left, JoinKeyword::Outer]))->right());
        self::assertFalse((new JoinOperator(false, [JoinKeyword::Right, new Name('x')]))->right());
        self::assertFalse((new JoinOperator(true))->right());
    }

    public function testRenderWritesACommaOrTheWordsBeforeJoin(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select * from t, u left wrong join t as t2 inner join u as u2');

        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(JoinChain::class, $query->statement->from);
        self::assertTrue($query->statement->from->steps[0]->operator->comma);
        self::assertSame([], $query->statement->from->steps[0]->operator->words);
        self::assertFalse($query->statement->from->steps[1]->operator->comma);
        self::assertSame(JoinKeyword::Left, $query->statement->from->steps[1]->operator->words[0]);
        self::assertInstanceOf(Name::class, $query->statement->from->steps[1]->operator->words[1]);
        self::assertSame('wrong', $query->statement->from->steps[1]->operator->words[1]->value);
        self::assertSame([JoinKeyword::Inner], $query->statement->from->steps[2]->operator->words);
        self::assertSame('SELECT * FROM t, u LEFT wrong JOIN t AS t2 INNER JOIN u AS u2', $query->toString());
    }

    public function testRejectsWordsAfterAComma(): void
    {
        $this->expectExceptionMessage('A join operator is a comma, JOIN, or JOIN after a join keyword and up to two more words.');

        new JoinOperator(true, [JoinKeyword::Left]);
    }

    public function testRejectsANameAsTheFirstWord(): void
    {
        $this->expectExceptionMessage('A join operator is a comma, JOIN, or JOIN after a join keyword and up to two more words.');

        new JoinOperator(false, [new Name('wrong'), JoinKeyword::Left]);
    }

    public function testRejectsMoreThanThreeWords(): void
    {
        $this->expectExceptionMessage('A join operator is a comma, JOIN, or JOIN after a join keyword and up to two more words.');

        new JoinOperator(false, [JoinKeyword::Natural, JoinKeyword::Left, JoinKeyword::Outer, JoinKeyword::Inner]);
    }
}
