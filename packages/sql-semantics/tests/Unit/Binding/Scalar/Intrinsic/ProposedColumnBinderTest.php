<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Intrinsic\ProposedColumnBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Reference\ProposedColumn;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProposedColumnBinder::class)]
#[Medium]
final class ProposedColumnBinderTest extends TestCase
{
    public function testBindKeepsAQualifiedColumn(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT VALUES(a.b)', strict: false);
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(ProposedColumn::class, $value);
        self::assertSame(['a', 'b'], $value->column->referenceParts());
        self::assertSame('SELECT VALUES (`a`.`b`)', $query->toString());
    }

    public function testBindLeavesOtherDialectsAlone(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('SELECT VALUES(b)');
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql));
        self::assertNull(ProposedColumnBinder::bind($tree->find('simple_expr')[0], $scope));
    }
}
