<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Selection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

#[CoversClass(\SqlSemantics\Platform\MySql\Rules\Routine\Selection\SelectionFacts::class)]
#[Medium]
final class SelectionFactsTest extends TestCase
{
    public function testDeriveKeepsTheProgramVariableAndProjectionAliasScopes(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(x INT) SELECT x AS v, (x, 3) ORDER BY (v,2)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection::class, $operation->body);
        $row = $operation->body->orderBy[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Row::class, $row);
        self::assertTrue($row->hasBinding());
        self::assertSame([], $operation->diagnostics());
    }

    public function testProjectionRetainsDeferredAndOrdinaryOperandFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(x INT) SELECT x, (x,2) ORDER BY 1');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection::class, $operation->body);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection::class, $operation->body->items[1]);
        self::assertTrue($operation->body->items[1]->expression->hasBinding());
    }

    public function testProjectionPreservesNoticeOrderWithoutSourceLocations(): void
    {
        $first = new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CharsetConversion(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(new \SqlSemantics\Statement\Identifier\Name('ucs2')));
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
        $second = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCharsetConversion($row, new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(new \SqlSemantics\Statement\Identifier\Name('utf8')));
        $selection = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($first), new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection($second)]);
        $procedure = new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), $selection);
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish((new Semantics(Dialect::MySql))->context([]), $procedure, new \SqlSemantics\Statement\Source\SourceMap());

        self::assertEquals([new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::Ucs2), new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::Utf8Alias)], $operation->warnings());
    }

    public function testOrderingBindsAnOutputAliasInsideADeferredRow(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(x INT) SELECT x AS v, (x, 3) ORDER BY (v,2)');
        self::assertSame([], $operation->diagnostics());
        self::assertSame('CREATE PROCEDURE p(x INT) SELECT x AS v, (x, 3) ORDER BY (v, 2)', $operation->toString());
    }
}
