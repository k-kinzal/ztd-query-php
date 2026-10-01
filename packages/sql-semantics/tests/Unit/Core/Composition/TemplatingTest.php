<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Composition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Composition\Templating;
use SqlSemantics\Core\CompositionException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Traversal;
use SqlSemantics\Statement\Writer;

#[CoversClass(Templating::class)]
#[UsesClass(\SqlSemantics\Core\Composition\Composition::class)]
#[UsesClass(\SqlSemantics\Core\Composition\Templates::class)]
#[UsesClass(\SqlSemantics\Core\Composition\Operands::class)]
#[UsesClass(Semantics::class)]
#[UsesClass(\SqlSemantics\Core\Language::class)]
#[UsesClass(CompositionException::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\LeafReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(Writer::class)]
#[UsesClass(Traversal::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Builder::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Expressions::class)]
#[Medium]
final class TemplatingTest extends TestCase
{
    public function testAnOperandThatFitsItsPositionIsPlacedItself(): void
    {
        $builder = (new Semantics(SqliteDialect::Sqlite))->builder();
        $operand = $builder->column('a');
        self::assertContains($operand, $builder->isNull($operand)->children());
        self::assertContains($operand, Traversal::find($builder->in($builder->column('b'), [$builder->integer(1), $operand]), Element::class));
    }

    public function testAnOperandThatCannotStandAtItsPositionIsRejected(): void
    {
        $semantics = new Semantics(SqliteDialect::Sqlite);
        $this->expectException(CompositionException::class);
        $semantics->builder()->isNull($semantics->analyze('SELECT 1')->command);
    }

    public function testATableThatWritesASlotNameIsNotTakenForASlot(): void
    {
        $builder = (new Semantics(SqliteDialect::Sqlite))->builder();
        $query = $builder->select([[$builder->column('slot1'), 'slot_0']], $builder->table('slot0'), $builder->column('slot_1'));
        self::assertSame('SELECT slot1 AS slot_0 FROM slot0 WHERE slot_1', Writer::render($query));
    }

    public function testAFunctionNameThatWritesASlotNameIsNotTakenForASlot(): void
    {
        $builder = (new Semantics(SqliteDialect::Sqlite))->builder();
        self::assertSame('slot1 ( 1 , evil )', Writer::render($builder->call('slot1', [$builder->integer(1), $builder->column('evil')])));
    }

    public function testTheTableOfASelectMustBeATableName(): void
    {
        $builder = (new Semantics(SqliteDialect::Sqlite))->builder();
        $this->expectException(CompositionException::class);
        $builder->select([[$builder->column('a'), null]], $builder->string('x'));
    }
}
