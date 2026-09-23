<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Definition\Foreign\MappingPrincipal;
use SqlSemantics\Model\Statement\Definition\PostgreSql\CreateUserMappingStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\MappingUsers::class)]
#[Medium]
final class MappingUsersTest extends TestCase
{
    #[TestWith(['USER', MappingPrincipal::CurrentUser])]
    #[TestWith(['CURRENT_USER', MappingPrincipal::CurrentUser])]
    #[TestWith(['CURRENT_ROLE', MappingPrincipal::CurrentRole])]
    #[TestWith(['SESSION_USER', MappingPrincipal::SessionUser])]
    #[TestWith(['PUBLIC', MappingPrincipal::PublicDefault])]
    #[TestWith(['"public"', MappingPrincipal::PublicDefault])]
    public function testReadRetainsASymbolicPrincipal(string $sqlPrincipal, MappingPrincipal $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER MAPPING FOR ' . $sqlPrincipal . ' SERVER remote');
        self::assertInstanceOf(CreateUserMappingStatement::class, $statement);
        self::assertSame($expected, $statement->target->user);
    }

    #[TestWith(['"CURRENT_USER"', 'CURRENT_USER'])]
    #[TestWith(['Alice', 'alice'])]
    #[TestWith(['"Alice"', 'Alice'])]
    #[TestWith(['"Public"', 'Public'])]
    #[TestWith(['"None"', 'None'])]
    public function testReadPreservesIdentifierQuotingAndCase(string $sqlPrincipal, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER MAPPING FOR ' . $sqlPrincipal . ' SERVER remote');
        self::assertInstanceOf(CreateUserMappingStatement::class, $statement);
        self::assertInstanceOf(NamedRole::class, $statement->target->user);
        self::assertSame($expected, $statement->target->user->name);
    }

    #[TestWith(['none'])]
    #[TestWith(['"none"'])]
    public function testReadDiagnosesTheReservedRoleName(string $sqlPrincipal): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('reserved name none');
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER MAPPING FOR ' . $sqlPrincipal . ' SERVER remote');
    }

}
