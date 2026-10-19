<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Expression;

use MySqlMemory\Command\Definition\Expression\GenerationCondition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(GenerationCondition::class)]
#[Small]
final class GenerationConditionTest extends TestCase
{
    public function testErrorNamesTheCompleteRetainedColumn(): void
    {
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), new M\Table\Column\Generation\DeferredGeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), new M\Call\KeywordCall(M\Call\KeywordFunction::User, [])));
        self::assertSame("Expression of generated column 'g' contains a disallowed function.", (new GenerationCondition())->error($column, 'd', 't')->getMessage());
    }

    public function testColumnsChecksAnEmptyDefinitionBeforeEarlierGenerationOperands(): void
    {
        $type = new M\Type\Integral(M\Type\Kind\IntegralKind::Int);
        $row = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), new M\Table\Column\Generation\DeferredGeneratedColumn($type, new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')])));
        $system = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('h')), new M\Table\Column\Generation\DeferredGeneratedColumn($type, new M\Variable\SystemVariable(new Name('version'))));
        $error = (new GenerationCondition())->columns([$row, $system], 'd', 't');
        self::assertSame(1033, $error->getCode());
        self::assertSame("Incorrect information in file: './d/t.frm'", $error->getMessage());
        self::assertSame("Incorrect information in file: ''", (new GenerationCondition())->columns([$system], 'd', 't', true)->getMessage());
    }
}
