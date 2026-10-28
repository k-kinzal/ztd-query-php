<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Recursion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Recursion\Constraints;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Constraints::class)]
#[Small]
final class ConstraintsTest extends TestCase
{
    public function testDefinitionDistinguishesNestedReferencesMissingUnionsAndAnchors(): void
    {
        $table = new M\Query\ExplicitTable(new QualifiedName(new Name('c')));
        $one = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]);
        $nested = new M\Query\Select([], [new M\Query\SelectExpression(new M\Expression\Subquery\ScalarSubquery($table))]);
        $rules = new Constraints();
        self::assertSame(M\Query\Problem\MisuseRule::RecursiveWithoutUnion, $rules->definition(new M\Query\With\CommonTableExpression(new Name('c'), [], $table))?->rule);
        self::assertSame(M\Query\Problem\MisuseRule::RecursiveReferencePosition, $rules->definition(new M\Query\With\CommonTableExpression(new Name('c'), [], $nested))?->rule);
        self::assertSame(M\Query\Problem\MisuseRule::RecursiveWithoutAnchor, $rules->definition(new M\Query\With\CommonTableExpression(new Name('c'), [], new M\Query\Set\SetOperation($table, M\Query\Set\SetOperator::Union, null, $one)))?->rule);
        self::assertNull($rules->definition(new M\Query\With\CommonTableExpression(new Name('c'), [], new M\Query\Set\SetOperation($one, M\Query\Set\SetOperator::Union, null, $table))));
    }

    public function testProblemChecksOnlyDefinitionsReachedByTheRead(): void
    {
        $table = new M\Query\ExplicitTable(new QualifiedName(new Name('c')));
        $with = new M\Query\With\With(true, [new M\Query\With\CommonTableExpression(new Name('c'), [], $table)]);
        $one = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]);
        self::assertNull((new Constraints())->problem(new M\Query\QueryExpression($with, $one)));
        self::assertSame(M\Query\Problem\MisuseRule::RecursiveWithoutUnion, (new Constraints())->problem(new M\Query\QueryExpression($with, new M\Query\ExplicitTable(new QualifiedName(new Name('c')))))?->rule);
    }

    public function testReadRoleExcludesAContradictionBeforeOperationConstruction(): void
    {
        $table = new M\Query\ExplicitTable(new QualifiedName(new Name('c')));
        $query = new M\Query\QueryExpression(new M\Query\With\With(true, [new M\Query\With\CommonTableExpression(new Name('c'), [], $table)]), new M\Query\ExplicitTable(new QualifiedName(new Name('c'))));
        self::assertSame($query, (new M\Routine\Program\Selection\DeferredQuery($query))->query);
        $this->expectExceptionMessage('A read statement has coherent fixed output columns.');
        new M\QueryRoot($query);
    }

    public function testDefinitionsRetainsLaterLexicalErrorsAfterAnEarlierDeferredCondition(): void
    {
        $one = new M\Query\With\CommonTableExpression(new Name('c'), [], new M\Query\ExplicitTable(new QualifiedName(new Name('c'))));
        $two = new M\Query\With\CommonTableExpression(new Name('d'), [], new M\Query\Select([], [new M\Query\SelectExpression(new M\Expression\Subquery\ScalarSubquery(new M\Query\ExplicitTable(new QualifiedName(new Name('d')))))]));
        $body = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))], new M\Relation\TableList([new M\Relation\TableReference(new QualifiedName(new Name('c'))), new M\Relation\TableReference(new QualifiedName(new Name('d')))]));
        $query = new M\Query\QueryExpression(new M\Query\With\With(true, [$one, $two]), $body);
        self::assertSame([$one, $two], (new Constraints())->definitions($query));
        self::assertSame(M\Query\Problem\MisuseRule::RecursiveReferencePosition, (new Constraints())->position($query)?->rule);
    }
    public function testPositionReportsNestedRecursionInARetainedInactiveStoredBranch(): void
    {
        $table = new M\Query\ExplicitTable(new QualifiedName(new Name('c')));
        $nested = new M\Query\Select([], [new M\Query\SelectExpression(new M\Expression\Subquery\ScalarSubquery($table))]);
        $query = new M\Query\QueryExpression(new M\Query\With\With(true, [new M\Query\With\CommonTableExpression(new Name('c'), [], $nested)]), new M\Query\ExplicitTable(new QualifiedName(new Name('c'))));
        $body = new M\Routine\Program\Flow\IfStatement([new M\Routine\Program\Flow\ConditionalBranch(new M\Literal\NumberLiteral('0'), [new M\Routine\Program\Selection\DeferredQuery($query)])]);
        self::assertSame(M\Query\Problem\MisuseRule::RecursiveReferencePosition, (new Constraints())->position($body)?->rule);
        $create = new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), $body);
        $statement = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish(new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), [new Name('test')]), $create);
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $statement);
        self::assertInstanceOf(M\Routine\Program\Flow\IfStatement::class, $statement->body);
        self::assertSame('A recursive table must be referenced only once per query block and not in a subquery', $statement->warnings()[0]->message());
    }
}
