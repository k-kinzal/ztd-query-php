<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression;

use MySqlMemory\Instance;
use MySqlMemory\Program\Expression\GenerationUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(GenerationUse::class)]
#[Medium]
final class GenerationUseTest extends TestCase
{
    public function testExecuteKeepsTheSameDefinitionAcrossDestinationStates(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $subquery = new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]));
        $column = new M\Table\Column\Generation\DeferredGeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, new M\Literal\NumberLiteral('2'), $subquery));
        $definition = new M\Routine\Program\Write\DeferredCreateTable(new QualifiedName(new Name('t')), [new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), $column)], ifNotExists: true);
        $session->query('CREATE TABLE t(a INT)');
        $session->text = 'unrelated input';
        self::assertSame(1, (new GenerationUse())->execute($definition, $session)->warnings());
        self::assertSame('unrelated input', $session->text);
        $session->query('DROP TABLE t');
        $this->expectExceptionMessage("near 'select 1)))' at line 1");
        (new GenerationUse())->execute($definition, $session);
    }
}
