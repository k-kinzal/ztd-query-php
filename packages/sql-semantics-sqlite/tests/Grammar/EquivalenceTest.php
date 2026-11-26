<?php

declare(strict_types=1);

namespace Tests\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier;
use SqlSemantics\Testing\Sqlite\Equivalence;
use SqlSemantics\Testing\Sqlite\Inspection;

/**
 * Checks that the grammar audit identifies structural differences between analyzed statements.
 */
#[CoversClass(Equivalence::class)]
#[Medium]
final class EquivalenceTest extends TestCase
{
    /**
     * Repeated analysis of one fixture gives equivalent structures.
     */
    public function testDifferenceIsNullForTwoAnalysesOfTheSameRequest(): void
    {
        $semantics = new Inspection();

        $difference = (new Equivalence())->difference($semantics->inspect('select a, b from t where a > 1')->statement, $semantics->inspect('SELECT a, b FROM t WHERE a > 1')->statement);

        self::assertNull($difference);
    }

    /**
     * Changing a scalar leaf identifies the changed value.
     */
    public function testDifferenceNamesAChangedScalarValue(): void
    {
        $left = new Select([new ResultColumn(new IntegerLiteral('1'))]);
        $right = new Select([new ResultColumn(new IntegerLiteral('2'))]);

        self::assertSame("statement->columns[0]->expression->digits ('1' against '2')", (new Equivalence())->difference($left, $right));
    }

    /**
     * Replacing a node with another class identifies the changed role.
     */
    public function testDifferenceNamesAChangedClass(): void
    {
        $left = new Select([new ResultColumn(new IntegerLiteral('1'))]);
        $right = new Select([new ResultColumn(new TextLiteral('1'))]);

        self::assertSame('statement->columns[0]->expression (' . IntegerLiteral::class . ' against ' . TextLiteral::class . ')', (new Equivalence())->difference($left, $right));
    }

    /**
     * Changing the length of a list identifies the changed operand.
     */
    public function testDifferenceNamesAListOfAnotherLength(): void
    {
        $left = new Select([new ResultColumn(new IntegerLiteral('1'))]);
        $right = new Select([new ResultColumn(new IntegerLiteral('1')), new ResultColumn(new IntegerLiteral('2'))]);

        self::assertSame('statement->columns (list of 1 against 2)', (new Equivalence())->difference($left, $right));
    }

    /**
     * Operator choices and missing aliases remain observable.
     */
    public function testDifferenceNamesAChangedEnumCaseAndADroppedAlias(): void
    {
        $plain = new Select([new ResultColumn(new IntegerLiteral('1'))]);
        $distinct = new Select([new ResultColumn(new IntegerLiteral('1'))], quantifier: SetQuantifier::Distinct);
        $aliased = new Select([new ResultColumn(new IntegerLiteral('1'), new Name('x'))]);

        self::assertSame("statement->quantifier ('null' against " . SetQuantifier::class . '::Distinct)', (new Equivalence())->difference($plain, $distinct));
        self::assertSame("statement->columns[0]->alias ('null' against " . var_export(Name::class, true) . ')', (new Equivalence())->difference($plain, $aliased));
    }

    /**
     * Property discovery records each declared member exactly once.
     */
    public function testMembersAnswersThePropertiesOfAClassOnce(): void
    {
        $equivalence = new Equivalence();

        $members = $equivalence->members(new IntegerLiteral('1'));

        self::assertSame(['digits'], array_map(static fn ($property): string => $property->getName(), $members));
        self::assertSame($members, $equivalence->members(new IntegerLiteral('2')));
    }

    /**
     * Failure descriptions identify the scalar value or enum case.
     */
    public function testDescribeSpellsScalarsAndEnumCases(): void
    {
        $equivalence = new Equivalence();

        self::assertSame("'a'", $equivalence->describe('a'));
        self::assertSame('true', $equivalence->describe(true));
        self::assertSame('3', $equivalence->describe(3));
        self::assertSame(Nullability::class . '::NotNull', $equivalence->describe(Nullability::NotNull));
    }
}
