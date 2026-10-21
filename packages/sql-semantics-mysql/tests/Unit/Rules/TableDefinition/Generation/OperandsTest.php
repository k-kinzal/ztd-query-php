<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\Operands;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Operands::class)]
#[Small]
final class OperandsTest extends TestCase
{
    public function testProblemKeepsFunctionCompletionBeforeItsEnclosingSubquery(): void
    {
        $call = new M\Call\KeywordCall(M\Call\KeywordFunction::User, []);
        $query = new M\Query\Select([], [new M\Query\SelectExpression($call)]);
        $expression = new M\Expression\Subquery\InQuery(new M\Literal\NumberLiteral('1'), $query);
        $problem = (new Operands())->problem($expression, new Name('g'));
        self::assertSame('user', $problem?->function);
        self::assertSame(M\Table\Problem\Generation\OperandKind::FunctionCall, $problem->kind);
    }

    public function testProblemRefusesFullTextBeforeItsSearchOperand(): void
    {
        $call = new M\Call\KeywordCall(M\Call\KeywordFunction::User, []);
        $match = new M\Expression\Access\FullTextSearch([new M\Name\ColumnUse(new Name('a'))], $call);
        self::assertSame('match', (new Operands())->problem($match, new Name('g'))?->function);
        self::assertSame('user', (new Operands())->problem(new M\Call\FunctionCall(new Name('CONCAT'), [new M\Call\CallArgument(new M\Call\KeywordCall(M\Call\KeywordFunction::User, [])), new M\Call\CallArgument($match)]), new Name('g'))?->function);
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), $call]);
        $deferred = new M\Routine\Program\Expression\Access\DeferredFullTextSearch([new M\Name\ColumnUse(new Name('a'))], $row);
        self::assertSame('match', (new Operands())->problem($deferred, new Name('g'))?->function);
    }

    public function testChildrenKeepsTheTestedOperandBeforeMembershipElements(): void
    {
        $match = new M\Expression\Access\FullTextSearch([new M\Name\ColumnUse(new Name('a'))], new M\Literal\StringLiteral(['x']));
        $call = new M\Call\KeywordCall(M\Call\KeywordFunction::User, []);
        $ordinary = new M\Expression\Predicate\InList($match, [$call]);
        self::assertSame([$match, $call], (new Operands())->children($ordinary));
        self::assertSame('match', (new Operands())->problem($ordinary, new Name('g'))?->function);
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $deferred = new M\Routine\Program\Expression\DeferredMembership($match, [$row]);
        self::assertSame([$match, $row], (new Operands())->children($deferred));
        self::assertSame('match', (new Operands())->problem($deferred, new Name('g'))?->function);
    }

    public function testProblemKeepsColumnReferencesOpenForResolution(): void
    {
        self::assertNull((new Operands())->problem(new M\Name\ColumnUse(new Name('a')), new Name('g')));
        self::assertSame(M\Table\Problem\Generation\OperandKind::Variable, (new Operands())->problem(new M\Variable\UserVariable(new Name('a')), new Name('g'))?->kind);
    }

    public function testRoleKeepsAParameterDistinctFromASessionVariable(): void
    {
        $problem = (new Operands())->role(new M\Literal\Parameter(), new Name('g'));
        self::assertSame(M\Table\Problem\Generation\OperandKind::Parameter, $problem?->kind);
        self::assertSame("Generated column 'g' excludes a parameter marker.", $problem->message());
    }
    public function testNativeKeepsAKeywordSpellingInItsGenericStoredRole(): void
    {
        self::assertFalse((new Operands())->native(new M\Call\FunctionCall(new Name('USER')), null));
        self::assertTrue((new Operands())->native(new M\Call\FunctionCall(new Name('RAND')), \SqlSemantics\Contract\GrammarRelease::MySql5744));
        self::assertSame('`USER`', (new Operands())->problem(new M\Call\FunctionCall(new Name('USER')), new Name('g'))?->function);
    }
    public function testRoleNamesTheStoredFunctionWithoutChangingItsResolution(): void
    {
        $call = new M\Call\FunctionCall(new Name('f'), [], new Name('d'));
        $problem = (new Operands())->role($call, new Name('g'));
        self::assertSame(M\Table\Problem\Generation\OperandKind::StoredFunction, $problem?->kind);
        self::assertSame('`d`.`f`', $problem->function);
    }
}
