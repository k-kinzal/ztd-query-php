<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\OrderPlacement;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerOrder;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(TriggerOrder::class)]
#[Medium]
final class TriggerOrderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, OrderPlacement, string}>
     */
    public static function providerRenderWritesThePlacementAndTheOtherTrigger(): iterable
    {
        yield 'MySQL 5.7 FOLLOWS' => [
            'mysql-5.7.44',
            'create trigger tr before insert on t for each row follows other set @a = 1',
            'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW FOLLOWS other SET @a = 1',
            OrderPlacement::Follows,
            'other',
        ];
        yield 'MySQL 8.0 PRECEDES a quoted name' => [
            'mysql-8.0.44',
            'create trigger tr after update on t for each row precedes `o x` set @a = 1',
            'CREATE TRIGGER tr AFTER UPDATE ON t FOR EACH ROW PRECEDES `o x` SET @a = 1',
            OrderPlacement::Precedes,
            'o x',
        ];
        yield 'MySQL 9.1 FOLLOWS a reserved word' => [
            'mysql-9.1.0',
            'create trigger tr after delete on t for each row follows `select` set @a = 1',
            'CREATE TRIGGER tr AFTER DELETE ON t FOR EACH ROW FOLLOWS `select` SET @a = 1',
            OrderPlacement::Follows,
            'select',
        ];
    }

    #[DataProvider('providerRenderWritesThePlacementAndTheOtherTrigger')]
    public function testRenderWritesThePlacementAndTheOtherTrigger(string $release, string $sql, string $expected, OrderPlacement $placement, string $other): void
    {
        $create = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTrigger::class, $statement);
        self::assertNotNull($statement->order);

        self::assertSame($placement, $statement->order->placement);
        self::assertSame($other, $statement->order->other->value);
        self::assertSame($expected, $create->toString());
    }

    public function testRenderWritesAConstructedOrder(): void
    {
        $out = new Output(new Codec((new Semantics(Dialect::MySql))->profile()->grammar));
        (new TriggerOrder(OrderPlacement::Precedes, new Name('select')))->render($out);

        self::assertSame('PRECEDES `select`', (new Lexical())->join($out->pieces()));
    }
}
