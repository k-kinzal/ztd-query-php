<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\Definitions;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Type\CreateShellTypeStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Definitions::class)]
#[Medium]
final class DefinitionsTest extends TestCase
{
    public function testBindRoutesByObjectClass(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertInstanceOf(CreateShellTypeStatement::class, $binder->bind('CREATE TYPE t'));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\CreateOperatorStatement::class, $binder->bind('CREATE OPERATOR === (function = f, rightarg = integer)'));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\Aggregate\CreateAggregateStatement::class, $binder->bind('CREATE OR REPLACE AGGREGATE a(*) (sfunc = f, stype = bigint)'));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\Locale\CopyCollationStatement::class, $binder->bind('CREATE COLLATION c FROM d'));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch\CreateTextSearchTemplateStatement::class, $binder->bind('CREATE TEXT SEARCH TEMPLATE t (lexize = l)'));
    }
}
