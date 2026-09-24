<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Conflict;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Write\Conflict\ActionKind;
use SqlSemantics\Model\Write\Conflict\AnyConflict;
use SqlSemantics\Model\Write\Conflict\ConstraintConflict;
use SqlSemantics\Model\Write\Conflict\ReplaceRow;

#[CoversClass(ReplaceRow::class)]
final class ReplaceRowTest extends TestCase
{
    public function testDerivesTheReplaceOperation(): void
    {
        $target = new AnyConflict();
        $source = new Node('conflict', 0, []);
        $action = new ReplaceRow($target, $source);
        self::assertSame(ActionKind::Replace, $action->action);
        self::assertSame($target, $action->target);
        self::assertSame($source, $action->source);
    }

    public function testKeepsAnExplicitConstraintSelector(): void
    {
        $action = new ReplaceRow(new ConstraintConflict('t_key'), new Node('conflict', 0, []));
        self::assertInstanceOf(ConstraintConflict::class, $action->target);
        self::assertSame('t_key', $action->target->name);
    }

    public function testCarriesNoAssignmentsOrRowPredicate(): void
    {
        $action = new ReplaceRow(new AnyConflict(), new Node('conflict', 0, []));
        self::assertFalse(property_exists($action, 'assignments'));
        self::assertFalse(property_exists($action, 'where'));
    }
}
