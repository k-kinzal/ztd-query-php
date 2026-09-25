<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\AddForeignOption;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Definition\Foreign\ServerVersionChange;
use SqlSemantics\Model\Statement\Definition\PostgreSql\AlterForeignServerStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\CreateForeignServerStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\ForeignServers::class)]
#[Medium]
final class ForeignServersTest extends TestCase
{
    public function testBindCreationRetainsAllDeclarationOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE SERVER IF NOT EXISTS remote TYPE 'relational' VERSION 'v1' FOREIGN DATA WRAPPER fdw OPTIONS (host 'local')");
        self::assertInstanceOf(CreateForeignServerStatement::class, $statement);
        self::assertSame('remote', $statement->name);
        self::assertSame('fdw', $statement->wrapper);
        self::assertSame("'relational'", $statement->serverType?->text);
        self::assertSame("'v1'", $statement->version?->text);
        self::assertTrue($statement->ifNotExists);
        self::assertSame('host', $statement->options[0]->name);
        self::assertSame("'local'", $statement->options[0]->value->text);
    }

    public function testBindCreationNormalizesExplicitAndImplicitAbsentVersions(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $implicit = $binder->bind('CREATE SERVER remote FOREIGN DATA WRAPPER fdw');
        $explicit = $binder->bind('CREATE SERVER remote VERSION NULL FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignServerStatement::class, $implicit);
        self::assertNull($implicit->serverType);
        self::assertNull($implicit->version);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($implicit), (new \SqlSemantics\SimpleSerializer())->serialize($explicit));
    }

    public function testBindAlterationKeepsVersionWhenOnlyOptionsChange(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER SERVER remote OPTIONS (add 'one', set 'two', DROP drop)");
        self::assertInstanceOf(AlterForeignServerStatement::class, $statement);
        self::assertSame(ServerVersionChange::Keep, $statement->version);
        self::assertInstanceOf(AddForeignOption::class, $statement->options[0]);
        self::assertInstanceOf(AddForeignOption::class, $statement->options[1]);
        self::assertInstanceOf(DropForeignOption::class, $statement->options[2]);
        self::assertSame('add', $statement->options[0]->option->name);
        self::assertSame('set', $statement->options[1]->option->name);
        self::assertSame('drop', $statement->options[2]->name);
    }

    public function testBindCreationRejectsDuplicateOptionNames(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('unique names');
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE SERVER remote FOREIGN DATA WRAPPER fdw OPTIONS (host 'a', host 'b')");
    }

    public function testTextReadsVersionRemovalAsAbsence(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SERVER remote VERSION NULL');
        $node = \SqlSemantics\Ast\Tree::outer($statement->origin->source, ['foreign_server_version'])[0];
        self::assertNull(\SqlSemantics\Binding\Statement\Definition\ForeignServers::text($node));
    }
}
