<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Recursion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Recursion\References;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(References::class)]
#[Small]
final class ReferencesTest extends TestCase
{
    public function testOfDistinguishesDirectAndNestedReferences(): void
    {
        $table = new M\Query\ExplicitTable(new QualifiedName(new Name('c')));
        $nested = new M\Query\Select([], [new M\Query\SelectExpression(new M\Expression\Subquery\ScalarSubquery($table))]);
        self::assertSame([[spl_object_id($table), 0]], (new References())->of($table, new Name('c')));
        self::assertSame([[spl_object_id($table), 1]], (new References())->of($nested, new Name('c')));
    }

    public function testVisitSkipsQualifiedNamesAndPreservesTheNameComparison(): void
    {
        $table = new M\Query\ExplicitTable(new QualifiedName(new Name('C')));
        $references = new References();
        self::assertSame([], $references->of($table, new Name('c')));
        self::assertCount(1, $references->of($table, new Name('c'), Comparison::AsciiInsensitive));
        self::assertSame([], $references->of(new M\Query\ExplicitTable(new QualifiedName(new Name('c'), new Name('d'))), new Name('c')));
    }

    public function testScopeKeepsShadowedAndUnusedDefinitionsOutOfTheReferences(): void
    {
        $one = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]);
        $self = new M\Query\ExplicitTable(new QualifiedName(new Name('c')));
        $shadowed = new M\Query\QueryExpression(new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [], $one)]), $self);
        $unused = new M\Query\QueryExpression(new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('unused'), [], $self)]), $one);
        self::assertSame([], (new References())->of($shadowed, new Name('c')));
        self::assertSame([], (new References())->of($unused, new Name('c')));
    }

    public function testWrapperKeepsParenthesesAndSetOperandsAtTheSameDepth(): void
    {
        $table = new M\Query\ExplicitTable(new QualifiedName(new Name('c')));
        $wrapped = new M\Query\ParenthesizedQuery($table);
        self::assertTrue((new References())->wrapper($wrapped));
        self::assertSame([[spl_object_id($table), 0]], (new References())->of($wrapped, new Name('c')));
    }
}
