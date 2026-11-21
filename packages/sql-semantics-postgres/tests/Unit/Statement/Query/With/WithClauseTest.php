<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\With\WithClause::class)]
#[Small]
final class WithClauseTest extends TestCase
{
    public function testDeriveCommonTablesBindsTheTables(): void
    {
        $derivation = new \SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation((new \SqlSemantics\Platform\PostgreSql\Platform())->context(new \SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172), null, [], true));
        $environment = (new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\WithClause([new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\CommonTableExpression(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('x'), new \SqlSemantics\Platform\PostgreSql\Statement\Query\Select([new \SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')))]), [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a')])], true))->deriveCommonTables($derivation, $derivation->environment());
        self::assertSame('a', $environment->commonTable(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('x'))?->shape->slots[0]->name?->value);
    }

    public function testRenderWritesRecursive(): void
    {
        $out = new \SqlSemantics\Platform\PostgreSql\Model\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\WithClause([new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\CommonTableExpression(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('x'), new \SqlSemantics\Platform\PostgreSql\Statement\Query\Select([new \SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')))]), [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a')])], true))->render($out);
        self::assertSame('WITH RECURSIVE x (a) AS (SELECT 1)', (new \SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical())->join($out->pieces()));
    }
}
