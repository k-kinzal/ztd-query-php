<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Configuration\Password;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Configuration\Password\LegacyAssignments;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Password\SetAccountOptionsStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LegacyAssignments::class)]
#[Medium]
final class LegacyAssignmentsTest extends TestCase
{
    public function testBindRetainsTheConcreteRequestOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET @x=1, PASSWORD='*hash'", strict: false);
        self::assertInstanceOf(SetAccountOptionsStatement::class, $statement);
        self::assertCount(2, $statement->operations);
    }
}
