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

    public function testProblemKeepsColumnReferencesOpenForResolution(): void
    {
        self::assertNull((new Operands())->problem(new M\Name\ColumnUse(new Name('a')), new Name('g')));
        self::assertSame(M\Table\Problem\Generation\OperandKind::Variable, (new Operands())->problem(new M\Variable\UserVariable(new Name('a')), new Name('g'))?->kind);
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
