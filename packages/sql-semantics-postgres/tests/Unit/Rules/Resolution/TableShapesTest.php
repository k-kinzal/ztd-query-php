<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Resolution\TableShapes::class)]
#[Small]
final class TableShapesTest extends TestCase
{
    public function testInputReportsASampleOfACommonTable(): void
    {
        $context = (new \SqlSemantics\Platform\PostgreSql\Platform())->context(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172), null, [], true);
        $derivation = new \SqlSemantics\Construction\Derivation($context);
        $fact = $derivation->query(new \SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression(new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\WithClause([new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\CommonTableExpression(new \SqlSemantics\Statement\Identifier\Name('x'), new \SqlSemantics\Platform\PostgreSql\Statement\Query\Select([new \SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')))]), [], null, null, null)], false), new \SqlSemantics\Platform\PostgreSql\Statement\Query\Select([new \SqlSemantics\Platform\PostgreSql\Statement\Query\StarTarget()], new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('x'))), null, [], new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableSample(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('system')]), [new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'))])))), $derivation->environment());
        self::assertSame('TABLESAMPLE clause can only be applied to tables and materialized views', $derivation->facts()->diagnostics[0]->message());
    }

    public function testImplicitIsEmptyWithoutADeclaration(): void
    {
        self::assertSame([], (new \SqlSemantics\Platform\PostgreSql\Rules\Resolution\TableShapes())->implicit(new \SqlSemantics\Statement\Fact\RelationFact(new \SqlSemantics\Statement\Shape\RowShape([]))));
    }
}
