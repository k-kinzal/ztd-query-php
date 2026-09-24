<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Procedural\Calls;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Procedural\CallStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Calls::class)]
#[Medium]
final class CallsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindRetainsNameAndArgumentsAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('CALL app.refresh(1 + 2, @since)', strict: false);
        self::assertInstanceOf(CallStatement::class, $statement);
        self::assertSame(['app', 'refresh'], $statement->procedure->parts);
        self::assertCount(2, $statement->arguments);
        self::assertSame('CALL `app`.`refresh`((1 + 2), @`since`)', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString(), strict: false)->toString());
        $bare = $binder->bind('CALL refresh');
        self::assertInstanceOf(CallStatement::class, $bare);
        self::assertSame([], $bare->arguments);
    }

    public function testBindDiagnosesAnEmptyProcedureName(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoutineName->message());
        $binder->bind('CALL ``.refresh()');
    }
}
