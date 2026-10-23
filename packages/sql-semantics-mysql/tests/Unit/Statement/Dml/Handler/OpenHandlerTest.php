<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\OpenHandler;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;

#[CoversClass(OpenHandler::class)]
#[Medium]
final class OpenHandlerTest extends TestCase
{
    public function testDeriveRelationUsesTheContextOfTheSameHandlerOccurrence(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE d.t(a INT, hidden INT INVISIBLE)')->declarations()[0];
        $column = new ColumnUse(new Name('hidden'));
        $handler = new OpenHandler(new Name('h'));
        $node = new HandlerScan($handler, \SqlSemantics\Platform\MySql\Statement\Dml\Handler\ScanDirection::First, $column);
        $before = new \SqlSemantics\Statement\Operation($semantics->context([$table]), $node);
        $settings = new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_bin'), handlers: ['h' => $table->name]);
        $after = new \SqlSemantics\Statement\Operation($semantics->context([$table], session: $settings), $node);
        $resolution = $after->facts->scalar($column)->resolution;

        self::assertInstanceOf(ConditionalColumn::class, $before->facts->scalar($column)->resolution);
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\ResolvedColumn::class, $resolution);
        self::assertSame($handler, $resolution->relation);
        self::assertSame($table->implicit[0]->column, $resolution->slot->declaration());
        self::assertSame('a', $after->field(0)->name?->value);
        self::assertTrue($after->facts->relation($handler)->shape->complete());
        self::assertFalse($before->facts->relation($handler)->shape->complete());
    }

    public function testDeriveRelationIsOpen(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('HANDLER h READ FIRST WHERE a');
        self::assertInstanceOf(HandlerScan::class, $operation->statement);

        self::assertFalse($operation->facts->relation($operation->statement->handler)->shape->complete());
        self::assertInstanceOf(ConditionalColumn::class, $operation->facts->scalar($operation->statement->where ?? new ColumnUse(new Name('a')))->resolution);
    }

    public function testRenderWritesTheName(): void
    {
        $out = new Output(new Codec((new Semantics(Dialect::MySql))->profile()->grammar));
        (new OpenHandler(new Name('h')))->render($out);

        self::assertSame('h', (new Lexical())->join($out->pieces()));
    }
}
