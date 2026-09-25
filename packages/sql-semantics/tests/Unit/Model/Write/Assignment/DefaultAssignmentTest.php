<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Assignment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Write\Assignment\DefaultAssignment::class)]
#[Medium]
final class DefaultAssignmentTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    public function testDestinationsIdentifiesTheColumnWhoseDefaultWillBeRead(Dialect $dialect): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER DEFAULT 7)'));
        $update = $binder->bind('UPDATE t SET id = DEFAULT');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateTableStatement::class, $update);
        $assignment = $update->writes[0];
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\DefaultAssignment::class, $assignment);
        self::assertSame([$assignment->target], $assignment->destinations());
        self::assertSame('id', $assignment->target->column()->columnBinding()?->column->name);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($update), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($update))));
    }
}
