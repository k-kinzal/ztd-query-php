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
        $definition = new M\Table\CreateTable(new QualifiedName(new Name('t')), [new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), $column)], ifNotExists: true);
        $session->query('CREATE TABLE t(a INT)');
        $session->text = 'unrelated input';
        self::assertSame(1, (new GenerationUse())->execute($definition, $session)->warnings());
        self::assertSame('unrelated input', $session->text);
        $session->query('DROP TABLE t');
        $this->expectExceptionMessage("near 'select 1)))' at line 1");
        (new GenerationUse())->execute($definition, $session);
    }
    public function testCreateExecutesTheSameCompleteDefinitionWithoutSourceSql(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $type = new M\Type\Integral(M\Type\Kind\IntegralKind::Int);
        $expression = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, new M\Variable\SystemVariable(new Name('version')), new M\Name\ColumnUse(new Name('a')));
        $definition = new M\Table\CreateTable(new QualifiedName(new Name('t')), [new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn($type)), new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), new M\Table\Column\Generation\DeferredGeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), $expression))], ifNotExists: true);
        $session->text = 'unrelated request';
        (new GenerationUse())->create($definition, $session, 'd');
        self::assertSame('unrelated request', $session->text);
        $session->query('INSERT INTO t(a) VALUES (7),(9)');
        $rows = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $rows);
        self::assertSame([['7', '7'], ['9', '9']], $rows->rows);
        self::assertSame(1, (new GenerationUse())->execute($definition, $session)->warnings());
        $session->query('DROP TABLE t');
        self::assertSame(0, (new GenerationUse())->execute($definition, $session)->warnings());
    }
}
