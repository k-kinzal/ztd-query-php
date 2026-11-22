<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Mutation\Delete;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\MySql\Ir\TableSource;

#[CoversClass(Delete::class)]
#[Small]
final class DeleteTest extends TestCase
{
    public function testToStringRetainsTheAbsoluteTargetAndOrderedPredicate(): void
    {
        $source = new TableSource(new Identifier('app'), new Identifier('accounts'), [new Identifier('id')]);
        $where = new IntegerComparison(IntegerOperator::Less, new ColumnReference(0), new IntegerLiteral('2'));

        self::assertSame('DELETE FROM `app`.`accounts` WHERE (`id` < 2)', (new Delete($source, $where))->toString());
    }

    public function testProfileIdUsesTheNativeDialectRelease(): void
    {
        $source = new TableSource(new Identifier('app'), new Identifier('accounts'), []);

        self::assertStringStartsWith('mysql-', (new Delete($source))->profileId());
    }

    public function testRejectsAnOutOfScopePredicateSlot(): void
    {
        $source = new TableSource(new Identifier('app'), new Identifier('accounts'), [new Identifier('id')]);
        $this->expectExceptionMessage('A DELETE predicate slot must belong to its target.');

        new Delete($source, new IntegerComparison(IntegerOperator::Less, new ColumnReference(1), new IntegerLiteral('2')));
    }
}
