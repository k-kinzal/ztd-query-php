<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Expression;

use MySqlMemory\Command\Definition\Expression\GenerationExecution;
use MySqlMemory\Command\Definition\Expression\GenerationRewrite;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(GenerationExecution::class)]
#[Medium]
final class GenerationExecutionTest extends TestCase
{
    /**
     * @return iterable<array{M\Routine\Program\Write\DeferredCreateTable}>
     */
    public static function providerDefinitions(): iterable
    {
        $expression = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, new M\Variable\SystemVariable(new Name('version')), new M\Literal\NumberLiteral('1'));
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), new M\Table\Column\Generation\DeferredGeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), $expression));
        $ordinary = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));
        yield [new M\Routine\Program\Write\DeferredCreateTable(new QualifiedName(new Name('t')), [$ordinary, $column])];
    }

    #[DataProvider('providerDefinitions')]
    public function testRunUsesThePreparationDatabaseAndTheTypedRowExpression(M\Routine\Program\Write\DeferredCreateTable $source): void
    {
        $session = (new Instance('5.7.44', [], ['d', 'other']))->connect(database: 'other');
        $definition = (new GenerationRewrite())->create($source);
        self::assertInstanceOf(M\Table\CreateTable::class, $definition);
        $session->text = 'unrelated invalid request';
        (new GenerationExecution())->run($definition, $session, 'd');
        self::assertSame('other', $session->variables->database);
        self::assertSame('unrelated invalid request', $session->text);
        $session->query('INSERT INTO d.t(a) VALUES (7),(9)');
        $rows = $session->query('SELECT * FROM d.t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $rows);
        self::assertSame([['7', '1'], ['9', '1']], $rows->rows);
    }

    #[DataProvider('providerDefinitions')]
    public function testDisplayUsesOriginalSemanticOperandsAfterCompilation(M\Routine\Program\Write\DeferredCreateTable $source): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $definition = (new GenerationRewrite())->create($source);
        self::assertInstanceOf(M\Table\CreateTable::class, $definition);
        $execution = new GenerationExecution();
        $execution->run($definition, $session, 'd');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $execution->display($table, $source, $session);
        self::assertSame('( + 1)', $table->definition->columns[1]->expression);
    }

    public function testVariablesResolvesUnknownNamesBeforeOmittingTheirValues(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $this->expectExceptionMessage("Unknown system variable 'unknown_generation_variable'");
        (new GenerationExecution())->variables(new M\Variable\SystemVariable(new Name('unknown_generation_variable')), $session);
    }
}
