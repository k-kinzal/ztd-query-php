<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Facts;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Fact\Warning;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Facts::class)]
#[Small]
final class FactsTest extends TestCase
{
    public function testWarningSubjectRetainsTheExactOccurrenceWithoutSourcePositions(): void
    {
        $subject = new IntegerLiteral('1');
        $warning = self::createStub(Warning::class);
        $other = self::createStub(Warning::class);
        $facts = new Facts([], [], [], [], null, [], [$warning, $other], warningSubjects: [[$warning, $subject]]);
        self::assertSame($subject, $facts->warningSubject($warning));
        self::assertNull($facts->warningSubject($other));
    }

    public function testAWarningCannotHaveTwoSemanticSubjects(): void
    {
        $subject = new IntegerLiteral('1');
        $warning = self::createStub(Warning::class);
        $this->expectExceptionMessage('Each notice subject identifies a distinct recorded warning.');
        new Facts([], [], [], [], null, [], [$warning], warningSubjects: [[$warning, $subject], [$warning, $subject]]);
    }

    public function testForQueryRetainsResolvedDependenciesAndOnlyItsOwnConditions(): void
    {
        $expression = new IntegerLiteral('1');
        $query = new Select([new ResultColumn($expression)]);
        $condition = self::createStub(Diagnostic::class);
        $sibling = self::createStub(Diagnostic::class);
        $scalar = new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        $output = new QueryFact([], Comparison::AsciiInsensitive, diagnostics: [$condition]);
        $facts = new Facts([[$expression, $scalar]], [], [[$query, $output]], [], null, [$condition, $sibling]);
        $selected = $facts->forQuery($query);
        self::assertSame([$condition], $selected->diagnostics);
        self::assertSame($output, $selected->output);
        self::assertSame($scalar, $selected->scalar($expression));
        self::assertSame([$condition, $sibling], $facts->diagnostics);
    }

    public function testReferencedRelationRetainsTheBoundOccurrence(): void
    {
        $name = new QualifiedName(new Name('t'), new Name('d'));
        $relation = new TableInput($name);
        $facts = new Facts([], [], [], [], null, [], references: [[$name, $relation]]);
        self::assertSame($relation, $facts->referencedRelation($name));
        self::assertNull($facts->referencedRelation(new QualifiedName(new Name('t'), new Name('d'))));
    }

    public function testWarningInstructionDistinguishesAnInstructionFromAnOuterNotice(): void
    {
        $instruction = new Select([new ResultColumn(new IntegerLiteral('1'))]);
        $nested = self::createStub(Warning::class);
        $outer = self::createStub(Warning::class);
        $facts = new Facts([], [], [], [], null, [], [$nested, $outer], [[$nested, $instruction]]);
        self::assertSame($instruction, $facts->warningInstruction($nested));
        self::assertNull($facts->warningInstruction($outer));
    }

    public function testIndexKeysPairsByNodeIdentity(): void
    {
        $first = new IntegerLiteral('1');
        $second = new IntegerLiteral('1');
        $fact = new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        $facts = new Facts([], [], [], [], null, []);
        $indexed = $facts->index([[$first, $fact], [$second, $fact]]);
        self::assertCount(2, $indexed);
        self::assertSame([$first, $fact], $indexed[spl_object_id($first)]);
        self::assertSame([$second, $fact], $indexed[spl_object_id($second)]);
    }

    public function testIndexRefusesANodeThatOccursTwice(): void
    {
        $node = new IntegerLiteral('1');
        $fact = new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        $this->expectExceptionMessage('A statement node occurs at one position only.');
        new Facts([[$node, $fact], [$node, $fact]], [], [], [], null, []);
    }

    public function testScalarReadsTheIndexedOccurrence(): void
    {
        $expression = new IntegerLiteral('1');
        $scalar = new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        $facts = new Facts([[$expression, $scalar]], [], [], [], null, []);
        self::assertSame($scalar, $facts->scalar($expression));
        $this->expectExceptionMessage('The expression is not part of this operation.');
        $facts->scalar(new IntegerLiteral('1'));
    }

    public function testRelationReadsTheIndexedOccurrence(): void
    {
        $relation = new TableInput(new QualifiedName(new Name('t')));
        $row = new RelationFact(new RowShape([]));
        $facts = new Facts([], [[$relation, $row]], [], [], null, []);
        self::assertSame($row, $facts->relation($relation));
        $this->expectExceptionMessage('The relation is not part of this operation.');
        $facts->relation(new TableInput(new QualifiedName(new Name('t'))));
    }

    public function testQueryReadsTheIndexedOccurrence(): void
    {
        $query = new Select([new ResultColumn(new IntegerLiteral('1'))]);
        $output = new QueryFact([], Comparison::AsciiInsensitive);
        $facts = new Facts([], [], [[$query, $output]], [], null, []);
        self::assertSame($output, $facts->query($query));
        $this->expectExceptionMessage('The query is not part of this operation.');
        $facts->query(new Select([new ResultColumn(new IntegerLiteral('1'))]));
    }

    public function testLocalizeTransfersEachBindingToItsOwnOccurrence(): void
    {
        $expression = new IntegerLiteral('1');
        $relation = new TableInput(new QualifiedName(new Name('t')));
        $query = new Select([new ResultColumn($expression)], from: $relation);
        $scalar = new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        $row = new RelationFact(new RowShape([]));
        $output = new QueryFact([], Comparison::AsciiInsensitive);
        $facts = new Facts([[$expression, $scalar]], [[$relation, $row]], [[$query, $output]], [], $output, []);
        self::assertFalse($expression->hasBinding());
        self::assertFalse($relation->hasBinding());
        self::assertFalse($query->hasBinding());
        $facts->localize();
        self::assertSame($scalar, $expression->scalarInformation());
        self::assertSame($row, $relation->relationInformation());
        self::assertSame($output, $query->queryInformation());
    }

    public function testCoversDistinguishesIndexedAndUnindexedOccurrences(): void
    {
        $expression = new IntegerLiteral('1');
        $relation = new TableInput(new QualifiedName(new Name('t')));
        $projection = new ResultColumn($expression);
        $query = new Select([$projection], from: $relation);
        $facts = new Facts([[$expression, new ScalarFact(new Known(Storage::Integer), Nullability::NotNull)]], [[$relation, new RelationFact(new RowShape([]))]], [[$query, new QueryFact([], Comparison::AsciiInsensitive)]], [], null, []);
        self::assertTrue($facts->covers($expression));
        self::assertTrue($facts->covers($relation));
        self::assertTrue($facts->covers($query));
        self::assertFalse($facts->covers($projection));
        self::assertFalse($facts->covers(new IntegerLiteral('1')));
    }
}
