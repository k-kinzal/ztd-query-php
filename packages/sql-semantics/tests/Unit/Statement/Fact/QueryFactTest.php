<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Fact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Shape\AbsentField;
use SqlSemantics\Statement\Shape\DependentField;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\UniqueField;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(QueryFact::class)]
#[Medium]
final class QueryFactTest extends TestCase
{
    public function testFieldsListsACompleteProjectionInOrder(): void
    {
        $first = new Field(0, new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull));
        $second = new Field(1, new OutputSlot(null, new Known(Storage::Text), Nullability::Nullable));

        $fact = new QueryFact([$first, $second], Comparison::Sensitive);

        self::assertSame([$first, $second], $fact->fields()?->items);
        self::assertTrue($fact->shape->complete());
        self::assertSame([$first->slot, $second->slot], $fact->shape->slots);
    }

    public function testFieldsIsNullWhileAStarCannotBeExpanded(): void
    {
        $missing = new UndeclaredRelation(new QualifiedName(new Name('t')));
        $field = new Field(0, new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull));

        $fact = new QueryFact([new OpenStar([$missing]), $field], Comparison::Sensitive);

        self::assertNull($fact->fields());
        self::assertFalse($fact->shape->complete());
        self::assertSame([$missing], $fact->shape->missing);
        self::assertSame([$field->slot], $fact->shape->slots);
    }

    public function testFieldsOfAnAnalyzedOpenStarStayNull(): void
    {
        $fact = (new Semantics(Dialect::Sqlite))->analyze('SELECT *, 1 AS one FROM t')->facts->output;

        self::assertNotNull($fact);
        self::assertInstanceOf(OpenStar::class, $fact->projection[0]);
        self::assertNull($fact->fields());
    }

    public function testLookupDistinguishesUniqueAndAbsentInACompleteShape(): void
    {
        $field = new Field(0, new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull));
        $fact = new QueryFact([$field], Comparison::AsciiInsensitive);

        $unique = $fact->lookup('A');

        self::assertInstanceOf(UniqueField::class, $unique);
        self::assertSame($field, $unique->field);
        self::assertInstanceOf(AbsentField::class, $fact->lookup('b'));
    }

    public function testLookupIsDependentInAnOpenShapeEvenWhenACandidateIsKnown(): void
    {
        $missing = new UndeclaredRelation(new QualifiedName(new Name('t')));
        $field = new Field(0, new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull));
        $fact = new QueryFact([new OpenStar([$missing]), $field], Comparison::Sensitive);

        $lookup = $fact->lookup('a');

        self::assertInstanceOf(DependentField::class, $lookup);
        self::assertSame([$field], $lookup->candidates);
        self::assertSame([$missing], $lookup->missing);
        self::assertSame('a', $lookup->name);
    }

    public function testLookupIsNotReachedForAProjectionItemThatIsNeitherFieldNorStar(): void
    {
        $this->expectExceptionMessage('A projection holds fields and open stars.');

        new QueryFact([new Name('a')], Comparison::Sensitive);
    }

    public function testLookupIsNotReachedForAProjectionThatIsNoList(): void
    {
        $field = new Field(0, new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull));

        $this->expectExceptionMessage('A projection is an ordered list.');

        new QueryFact(['a' => $field], Comparison::Sensitive);
    }
}
