<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\TypeSystemCommands;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Domain\CreateDomainStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TypeSystemCommands::class)]
#[Medium]
final class TypeSystemCommandsTest extends TestCase
{
    public function testBindRoutesTypeSystemDefinitions(): void
    {
        self::assertInstanceOf(CreateDomainStatement::class, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d AS integer'));
    }

    public function testObjectsRoutesOperatorCollationAndTextSearchCommands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\DropOperatorsStatement::class, $binder->bind('DROP OPERATOR + (integer, integer)'));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\Locale\RefreshCollationVersionStatement::class, $binder->bind('ALTER COLLATION c REFRESH VERSION'));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch\DropTextSearchMappingStatement::class, $binder->bind('ALTER TEXT SEARCH CONFIGURATION c DROP MAPPING FOR word'));
    }
}
