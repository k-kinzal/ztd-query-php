<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use ArrayIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Validation\ValueGraph;

#[CoversClass(ValueGraph::class)]
#[Small]
final class ValueGraphTest extends TestCase
{
    public function testObjectsListsEveryReachableObjectOnce(): void
    {
        $name = new Name('a');
        $literal = new IntegerLiteral('1');
        $statement = new Select([new ResultColumn($literal, $name)], null, null, [], null, [], [], null, SetQuantifier::Distinct);

        $objects = (new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Platform\\Sqlite\\Statement\\']))->objects($statement);

        self::assertCount(5, $objects);
        self::assertSame($statement, $objects[0]);
        self::assertContains($literal, $objects);
        self::assertContains($name, $objects);
        self::assertContains(SetQuantifier::Distinct, $objects);
    }

    public function testObjectsRefusesAClassOutsideTheAdmittedNamespaces(): void
    {
        $graph = new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Platform\\Sqlite\\Statement\\Query\\']);

        $this->expectExceptionMessage('A value of class ' . IntegerLiteral::class . ' is not part of the closed semantic value domain.');

        $graph->objects(new Select([new ResultColumn(new IntegerLiteral('1'))]));
    }

    public function testObjectsRefusesAPhpReferenceInsideAList(): void
    {
        $hidden = [1];
        $alias = &$hidden[0];
        $relation = new VisibleRelation(new TableInput(new QualifiedName(new Name('t'))), new RowShape([]), null, null, $hidden);

        $this->expectExceptionMessage('A semantic value holds no PHP reference.');

        (new ValueGraph(['SqlSemantics\\']))->objects($relation);
    }

    public function testObjectsRefusesAnAnonymousClassWhateverInterfaceItImplements(): void
    {
        $foreign = new class () implements Relation {
            public function render(Output $out): void
            {
            }

            public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
            {
                return new RelationFact(new RowShape([]));
            }
        };
        $statement = new Select([new ResultColumn(new IntegerLiteral('1'))], $foreign);

        $this->expectExceptionMessageMatches('/Semantic value class SqlSemantics\\\\Statement\\\\Relation@anonymous.* must be final\\.$/');

        (new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Platform\\Sqlite\\Statement\\']))->objects($statement);
    }

    public function testMembersRefusesAClassThatIsNotFinal(): void
    {
        $graph = new ValueGraph([ArrayIterator::class]);

        $this->expectExceptionMessage('Semantic value class ArrayIterator must be final.');

        $graph->members(new ArrayIterator([]));
    }

    public function testMembersRefusesAClassWithoutTheSnapshotTrait(): void
    {
        $graph = new ValueGraph(['SqlSemantics\\']);

        $this->expectExceptionMessage('Semantic value class ' . SearchPath::class . ' must close cloning, dynamic properties and unserialization with the Snapshot trait.');

        $graph->members(new SearchPath('main'));
    }

    public function testMembersRefusesAClassWithWritableProperties(): void
    {
        $graph = new ValueGraph(['SqlSemantics\\']);

        $this->expectExceptionMessage('Semantic value class ' . Leaves::class . ' must have readonly properties only.');

        $graph->members(new Leaves());
    }

    public function testMembersAnswersTheReadonlyPropertiesAndNothingForAnEnumCase(): void
    {
        $graph = new ValueGraph(['SqlSemantics\\Platform\\Sqlite\\Statement\\']);

        self::assertSame(['digits'], array_map(static fn ($property): string => $property->getName(), $graph->members(new IntegerLiteral('1'))));
        self::assertSame([], $graph->members(SetQuantifier::All));
    }
}
