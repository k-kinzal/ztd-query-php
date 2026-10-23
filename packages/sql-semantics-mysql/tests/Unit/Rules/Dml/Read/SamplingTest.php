<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml\Read;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Dml\Read\Sampling;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Sampling::class)]
#[Small]
final class SamplingTest extends TestCase
{
    public function testOfRetainsSamplingInsideAnAssignmentSubquery(): void
    {
        $source = new M\Relation\TableReference(new QualifiedName(new Name('u')), sample: new M\Relation\Hint\TableSample(M\Relation\Hint\SamplingMethod::System, new M\Literal\NumberLiteral('5')));
        $read = new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))], $source));
        $assignment = new M\Dml\Assignment(new M\Name\ColumnUse(new Name('a')), $read);
        $write = new M\Routine\Program\Write\DeferredUpdate(null, false, false, [new M\Relation\TableReference(new QualifiedName(new Name('t')))], [$assignment]);

        self::assertSame(M\Dml\Problem\WriteRule::SampledWrite, (new Sampling())->of($write)?->rule);
        self::assertSame($read, $write->assignments[0]->value);
    }

    public function testInputsActivatesOnlyReferencedCommonTableDefinitions(): void
    {
        $source = new M\Relation\TableReference(new QualifiedName(new Name('u')), sample: new M\Relation\Hint\TableSample(M\Relation\Hint\SamplingMethod::Bernoulli, new M\Literal\NumberLiteral('5')));
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))], $source);
        $with = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('q'), [], $query)]);
        $rules = new Sampling();

        self::assertNull($rules->inputs([new M\Relation\TableReference(new QualifiedName(new Name('t')))], $with));
        self::assertNull($rules->inputs([new M\Relation\TableReference(new QualifiedName(new Name('q'), new Name('d')))], $with));
        self::assertSame(M\Dml\Problem\WriteRule::SampledWrite, $rules->inputs([new M\Relation\TableReference(new QualifiedName(new Name('q')))], $with)?->rule);
    }

    /**
     * @return iterable<string, array{Closure(): M\Routine\Program\ProgramStatement}>
     */
    public static function providerOrdinaryWrites(): iterable
    {
        $name = new QualifiedName(new Name('t'));
        $sampled = new M\Relation\TableReference($name, sample: new M\Relation\Hint\TableSample(M\Relation\Hint\SamplingMethod::System, new M\Literal\NumberLiteral('5')));
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))], $sampled);
        $where = new M\Expression\Subquery\Exists($query);
        $assignment = new M\Dml\Assignment(new M\Name\ColumnUse(new Name('a')), new M\Literal\NumberLiteral('1'));
        yield 'update-input' => [static fn (): M\Routine\Program\ProgramStatement => new M\Routine\Program\Write\DeferredUpdate(null, false, false, [$sampled], [$assignment])];
        yield 'update-predicate' => [static fn (): M\Routine\Program\ProgramStatement => new M\Routine\Program\Write\DeferredUpdate(null, false, false, [new M\Relation\TableReference($name)], [$assignment], $where)];
        yield 'delete-predicate' => [static fn (): M\Routine\Program\ProgramStatement => new M\Routine\Program\Write\DeferredDelete(null, [], new M\Dml\WriteTarget($name), $where)];
        yield 'multiple-delete-input' => [static fn (): M\Routine\Program\ProgramStatement => new M\Routine\Program\Write\DeferredMultipleDelete(null, [], new M\Dml\Target\DeletionInput([$sampled], [$name]), M\Dml\MultipleDeleteForm::BeforeFrom)];
    }

    /**
     * @param Closure(): M\Routine\Program\ProgramStatement $construct A retained write with a reached sampling clause
     */
    #[DataProvider('providerOrdinaryWrites')]
    public function testOfIdentifiesRetainedSamplingBeforeOperationConstruction(Closure $construct): void
    {
        self::assertSame(M\Dml\Problem\WriteRule::SampledWrite, (new Sampling())->of($construct())?->rule);
    }
}
