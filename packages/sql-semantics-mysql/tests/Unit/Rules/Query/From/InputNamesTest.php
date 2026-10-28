<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\From\InputNames;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(InputNames::class)]
#[Small]
final class InputNamesTest extends TestCase
{
    public function testProblemRetainsTheSecondInputIdentity(): void
    {
        $name = new QualifiedName(new Name('t'));
        $first = new M\Relation\TableReference($name);
        $second = new M\Relation\TableReference($name);
        $query = new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableList([$first, $second]));
        $problem = (new InputNames())->problem($query);
        self::assertInstanceOf(M\Server\Problem\NonUniqueTable::class, $problem);
        self::assertSame($second, $problem->relation);
        self::assertSame($name->name, $problem->alias);
    }

    public function testProblemOrdersTheRepeatedOccurrenceBeforeLaterNestedInputs(): void
    {
        $inner = new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableList([
            new M\Relation\TableReference(new QualifiedName(new Name('b'))),
            new M\Relation\TableReference(new QualifiedName(new Name('b'))),
        ]));
        $first = new M\Relation\TableReference(new QualifiedName(new Name('a')));
        $second = new M\Relation\TableReference(new QualifiedName(new Name('a')));
        $nested = new M\Relation\DerivedTable($inner, new Name('c'));
        $query = new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableList([$first, $second, $nested]));
        self::assertSame($second, (new InputNames())->problem($query)?->relation);
        $reordered = new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableList([$first, $nested, $second]));
        self::assertSame('b', (new InputNames())->problem($reordered)?->alias->value);
    }

    public function testProblemRejectsRepeatedDerivedAliasesInTheReadRole(): void
    {
        $body = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]);
        $first = new M\Relation\DerivedTable($body, new Name('a'));
        $second = new M\Relation\DerivedTable(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('2'))]), new Name('a'));
        $query = new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableList([$first, $second]));
        self::assertSame($second, (new InputNames())->problem($query)?->relation);
        $this->expectExceptionMessage('coherent fixed output columns');
        new M\QueryRoot($query);
    }

    public function testProblemLeavesDistinctDatabaseNamespacesForBinding(): void
    {
        $query = new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableList([
            new M\Relation\TableReference(new QualifiedName(new Name('t'), new Name('one'))),
            new M\Relation\TableReference(new QualifiedName(new Name('t'), new Name('two'))),
        ]));
        self::assertNull((new InputNames())->problem($query));
        self::assertSame($query, (new M\QueryRoot($query))->query);
    }

    public function testProblemIgnoresAnUnusedDefinitionButChecksItsActiveReference(): void
    {
        $inner = new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableList([
            new M\Relation\TableReference(new QualifiedName(new Name('t'))),
            new M\Relation\TableReference(new QualifiedName(new Name('t'))),
        ]));
        $with = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [], $inner)]);
        $unused = new M\Query\QueryExpression($with, new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]));
        $active = new M\Query\QueryExpression($with, new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('c')))));
        self::assertNull((new InputNames())->problem($unused));
        self::assertSame($unused, (new M\QueryRoot($unused))->query);
        self::assertInstanceOf(M\Server\Problem\NonUniqueTable::class, (new InputNames())->problem($active));
        $this->expectExceptionMessage('coherent fixed output columns');
        new M\QueryRoot($active);
    }

    public function testProblemPreventsAnOrdinaryScalarWithFixedInputCollisions(): void
    {
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))], new M\Relation\TableList([
            new M\Relation\TableReference(new QualifiedName(new Name('t'))),
            new M\Relation\TableReference(new QualifiedName(new Name('t'))),
        ]));
        $this->expectExceptionMessage('coherent fixed read columns');
        new M\Expression\Subquery\ScalarSubquery($query);
    }

    public function testProblemRetainsADeclarationContainingAnUnreachableCollision(): void
    {
        $query = new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableList([
            new M\Relation\TableReference(new QualifiedName(new Name('t'))),
            new M\Relation\TableReference(new QualifiedName(new Name('t'))),
        ]));
        $instruction = new M\Routine\Program\Selection\DeferredQuery($query);
        $body = new M\Routine\Program\Flow\IfStatement([new M\Routine\Program\Flow\ConditionalBranch(new M\Literal\BooleanLiteral(false), [$instruction])]);
        $create = new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), $body);
        $statement = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish(new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), [new Name('test')]), $create);
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $statement);
        self::assertInstanceOf(M\Routine\Program\Flow\IfStatement::class, $statement->body);
        self::assertSame("Not unique table/alias: 't'.", $statement->warnings()[0]->message());
    }
}
