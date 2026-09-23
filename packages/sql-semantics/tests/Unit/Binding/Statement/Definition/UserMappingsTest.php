<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Definition\Foreign\AddForeignOption;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Definition\Foreign\MappingPrincipal;
use SqlSemantics\Model\Definition\Foreign\SetForeignOption;
use SqlSemantics\Model\Statement\Definition\PostgreSql\AlterUserMappingStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\CreateUserMappingStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\DropUserMappingStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\UserMappings::class)]
#[Medium]
final class UserMappingsTest extends TestCase
{
    public function testBindCreationPreservesTheExistencePolicyAndInitialOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE USER MAPPING IF NOT EXISTS FOR alice SERVER remote OPTIONS (user 'remote_alice')");
        self::assertInstanceOf(CreateUserMappingStatement::class, $statement);
        self::assertTrue($statement->ifNotExists);
        self::assertInstanceOf(NamedRole::class, $statement->target->user);
        self::assertSame('alice', $statement->target->user->name);
        self::assertSame('remote', $statement->target->server);
        self::assertSame('user', $statement->options[0]->name);
        self::assertSame("'remote_alice'", $statement->options[0]->value->text);
        self::assertSame([], $statement->diagnostics);
    }

    public function testBindAlterationPreservesAllThreeOptionActions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER USER MAPPING FOR USER SERVER remote OPTIONS (ADD x 'new', SET y 'value', DROP z)");
        self::assertInstanceOf(AlterUserMappingStatement::class, $statement);
        self::assertInstanceOf(AddForeignOption::class, $statement->options[0]);
        self::assertInstanceOf(SetForeignOption::class, $statement->options[1]);
        self::assertInstanceOf(DropForeignOption::class, $statement->options[2]);
        self::assertSame('x', $statement->options[0]->option->name);
        self::assertSame('y', $statement->options[1]->option->name);
        self::assertSame('z', $statement->options[2]->name);
    }

    public function testBindRemovalHasItsOwnExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP USER MAPPING IF EXISTS FOR SESSION_USER SERVER remote');
        self::assertInstanceOf(DropUserMappingStatement::class, $statement);
        self::assertTrue($statement->ifExists);
        self::assertSame(MappingPrincipal::SessionUser, $statement->target->user);
        self::assertSame('remote', $statement->target->server);
    }

    public function testBindDiagnosesDuplicateInitialOptions(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('unique names');
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE USER MAPPING FOR USER SERVER remote OPTIONS (user 'one', user 'two')");
    }

    public function testBindDoesNotTreatAQuotedRoleAsAnExistenceClause(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP USER MAPPING FOR "IF" SERVER "EXISTS"');
        self::assertInstanceOf(DropUserMappingStatement::class, $statement);
        self::assertFalse($statement->ifExists);
        self::assertInstanceOf(NamedRole::class, $statement->target->user);
        self::assertSame('IF', $statement->target->user->name);
        self::assertSame('EXISTS', $statement->target->server);
    }

}
