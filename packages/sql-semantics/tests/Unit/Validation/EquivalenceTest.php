<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Validation\Equivalence;

#[CoversClass(Equivalence::class)]
#[CoversClass(\SqlSemantics\Validation\ReferencePairs::class)]
#[Medium]
final class EquivalenceTest extends TestCase
{
    public function testExactComparisonDistinguishesSharedFromEqualLookingOccurrences(): void
    {
        $shared = new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter();
        $first = new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter();
        $second = new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter();
        $comparison = new Equivalence();

        self::assertNull($comparison->difference([$shared, $shared], [$first, $second]));
        self::assertNotNull($comparison->difference([$shared, $shared], [$first, $second], true));
        self::assertNotNull($comparison->difference([$first, $second], [$shared, $shared], true));
        self::assertNull($comparison->difference([$shared, $shared], [$first, $first], true));
    }

    public function testExactComparisonIncludesInheritedStatementInformation(): void
    {
        $first = new \SqlSemantics\Statement\Script([], statementInformation: new \SqlSemantics\Statement\StatementSemantics(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847)));
        $second = new \SqlSemantics\Statement\Script([], statementInformation: new \SqlSemantics\Statement\StatementSemantics(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql910)));
        $comparison = new Equivalence();
        self::assertNull($comparison->difference($first, $second));
        self::assertNotNull($comparison->difference($first, $second, true));
    }

    public function testExactComparisonRetainsBindingsBetweenRelatedRoots(): void
    {
        $first = new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter();
        $second = new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter();
        $otherFirst = new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter();
        $otherSecond = new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter();
        $left = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($first), new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($second)]));
        $right = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($otherFirst), new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($otherSecond)]));
        $origins = new \SqlSemantics\Statement\Source\SourceMap([new \SqlSemantics\Statement\Source\Origin($first, 7, 1), new \SqlSemantics\Statement\Source\Origin($second, 10, 1)]);
        $correct = new \SqlSemantics\Statement\Source\SourceMap([new \SqlSemantics\Statement\Source\Origin($otherFirst, 7, 1), new \SqlSemantics\Statement\Source\Origin($otherSecond, 10, 1)]);
        $swapped = new \SqlSemantics\Statement\Source\SourceMap([new \SqlSemantics\Statement\Source\Origin($otherSecond, 7, 1), new \SqlSemantics\Statement\Source\Origin($otherFirst, 10, 1)]);
        $comparison = new Equivalence();

        self::assertNull($comparison->difference($left, $right, true));
        self::assertNull($comparison->difference($origins, $swapped, true));
        self::assertNull($comparison->difference(['structure' => $left, 'sources' => $origins], ['structure' => $right, 'sources' => $correct], true));
        self::assertNotNull($comparison->difference(['structure' => $left, 'sources' => $origins], ['structure' => $right, 'sources' => $swapped], true));
    }

    public function testExactComparisonPreservesTheReferencedRelationAndSlot(): void
    {
        $relation = new \SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t')));
        $other = new \SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t')));
        $unrelated = new \SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t')));
        $slot = new \SqlSemantics\Statement\Shape\OutputSlot(new Name('a'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Integer), Nullability::NotNull);
        $otherSlot = new \SqlSemantics\Statement\Shape\OutputSlot(new Name('a'), new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Integer), Nullability::NotNull);
        $left = ['relation' => $relation, 'slot' => $slot, 'reference' => new \SqlSemantics\Statement\Reference\Column\ResolvedColumn($relation, $slot)];
        $right = ['relation' => $other, 'slot' => $otherSlot, 'reference' => new \SqlSemantics\Statement\Reference\Column\ResolvedColumn($other, $otherSlot)];
        $wrong = ['relation' => $other, 'slot' => $otherSlot, 'reference' => new \SqlSemantics\Statement\Reference\Column\ResolvedColumn($unrelated, $otherSlot)];

        self::assertNull((new Equivalence())->difference($left, $right, true));
        self::assertNotNull((new Equivalence())->difference($left, $wrong, true));
    }

    public function testComparedMembersIncludesUnspecifiedQuoteChoicesOnlyWhenExact(): void
    {
        $comparison = new Equivalence();
        $generated = new Name('a');
        $quoted = new Name('a', \SqlSemantics\Statement\Identifier\NameQuote::Backtick);
        $exact = array_map(static fn (ReflectionProperty $property): string => $property->getName(), $comparison->comparedMembers($generated, $quoted, true));
        $corresponding = array_map(static fn (ReflectionProperty $property): string => $property->getName(), $comparison->comparedMembers($generated, $quoted, false));
        self::assertContains('quote', $exact);
        self::assertNotContains('quote', $corresponding);
    }

    public function testDifferenceCanRequireExactLocationsAndQuoteChoicesBeforeSharing(): void
    {
        $comparison = new Equivalence();
        $left = new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['42'], location: new \SqlSemantics\Statement\Source\Span(12, 4));
        $moved = new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['42'], location: new \SqlSemantics\Statement\Source\Span(25, 4));
        self::assertNull($comparison->difference($left, $moved));
        self::assertNotNull($comparison->difference($left, $moved, true));
        $generated = new Name('a');
        $quoted = new Name('a', \SqlSemantics\Statement\Identifier\NameQuote::Backtick);
        self::assertNull($comparison->difference($generated, $quoted));
        self::assertNotNull($comparison->difference($generated, $quoted, true));
        self::assertNull($comparison->difference($quoted, new Name('a', \SqlSemantics\Statement\Identifier\NameQuote::Backtick), true));
    }

    public function testDifferenceIsNullForTwoAnalysesOfTheSameRequest(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        $left = $semantics->analyze('select a, b from t where a > 1');
        $right = $semantics->analyze('SELECT a, b FROM t WHERE a > 1');
        $difference = (new Equivalence())->difference($left, $right);

        self::assertNull($difference);
    }

    public function testDifferenceNamesAChangedScalarValue(): void
    {
        $left = new Select([new ResultColumn(new IntegerLiteral('1'))]);
        $right = new Select([new ResultColumn(new IntegerLiteral('2'))]);

        self::assertSame("statement->columns[0]->expression->digits ('1' against '2')", (new Equivalence())->difference($left, $right));
    }

    public function testDifferenceNamesAChangedClass(): void
    {
        $left = new Select([new ResultColumn(new IntegerLiteral('1'))]);
        $right = new Select([new ResultColumn(new TextLiteral('1'))]);

        self::assertSame('statement->columns[0]->expression (' . IntegerLiteral::class . ' against ' . TextLiteral::class . ')', (new Equivalence())->difference($left, $right));
    }

    public function testDifferenceNamesAListOfAnotherLength(): void
    {
        $left = new Select([new ResultColumn(new IntegerLiteral('1'))]);
        $right = new Select([new ResultColumn(new IntegerLiteral('1')), new ResultColumn(new IntegerLiteral('2'))]);

        self::assertSame('statement->columns (list of 1 against 2)', (new Equivalence())->difference($left, $right));
    }

    public function testDifferenceNamesAChangedEnumCaseAndADroppedAlias(): void
    {
        $plain = new Select([new ResultColumn(new IntegerLiteral('1'))]);
        $distinct = new Select([new ResultColumn(new IntegerLiteral('1'))], quantifier: SetQuantifier::Distinct);
        $aliased = new Select([new ResultColumn(new IntegerLiteral('1'), new Name('x'))]);

        self::assertSame("statement->quantifier ('null' against " . SetQuantifier::class . '::Distinct)', (new Equivalence())->difference($plain, $distinct));
        self::assertSame("statement->columns[0]->alias ('null' against " . var_export(Name::class, true) . ')', (new Equivalence())->difference($plain, $aliased));
    }

    public function testMembersAnswersThePropertiesOfAClassOnce(): void
    {
        $equivalence = new Equivalence();

        $members = $equivalence->members(new IntegerLiteral('1'));

        self::assertSame(['digits'], array_map(static fn ($property): string => $property->getName(), $members));
        self::assertSame($members, $equivalence->members(new IntegerLiteral('2')));
    }

    public function testDifferenceExcludesLocationsButKeepsLiteralValues(): void
    {
        $left = new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['42'], location: new \SqlSemantics\Statement\Source\Span(12, 4));
        $moved = new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['42'], location: new \SqlSemantics\Statement\Source\Span(25, 4));
        $generated = new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['42']);
        $changed = new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['7']);

        self::assertNull((new Equivalence())->difference($left, $moved));
        self::assertNull((new Equivalence())->difference($left, $generated));
        self::assertSame("statement->segments[0] ('42' against '7')", (new Equivalence())->difference($left, $changed));
    }

    public function testDescribeSpellsScalarsAndEnumCases(): void
    {
        $equivalence = new Equivalence();

        self::assertSame("'a'", $equivalence->describe('a'));
        self::assertSame('true', $equivalence->describe(true));
        self::assertSame('3', $equivalence->describe(3));
        self::assertSame(Nullability::class . '::NotNull', $equivalence->describe(Nullability::NotNull));
    }
    public function testDifferenceRetainsExplicitNameDelimiters(): void
    {
        $bare = new Name('BINLOG', \SqlSemantics\Statement\Identifier\NameQuote::Bare);
        $quoted = new Name('BINLOG', \SqlSemantics\Statement\Identifier\NameQuote::Backtick);
        self::assertNotNull((new Equivalence())->difference($bare, $quoted));
        self::assertNull((new Equivalence())->difference(new Name('BINLOG'), $quoted));
        self::assertNotNull((new Equivalence())->difference(new Name('other'), $quoted));
    }

}
