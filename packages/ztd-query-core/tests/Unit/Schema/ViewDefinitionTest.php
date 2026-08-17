<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\ViewDefinition;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(ViewDefinition::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(SqlTokenKind::class)]
#[UsesClass(SqlTokenStream::class)]
final class ViewDefinitionTest extends TestCase
{
    public function testFromQueryIsPublicAndNormalizesOuterWhitespace(): void
    {
        $method = new \ReflectionMethod(ViewDefinition::class, 'fromQuery');
        $definition = ViewDefinition::fromQuery("  SELECT * FROM users;  \n");

        self::assertTrue($method->isPublic());
        self::assertSame('SELECT * FROM users', $definition->query);
    }

    public function testExtractsQueryAndDependenciesFromCreateStatement(): void
    {
        $definition = ViewDefinition::fromCreateStatement(
            'CREATE VIEW active_users AS SELECT users.id FROM public.users JOIN roles ON roles.id = users.role_id;',
        );

        self::assertNotNull($definition);
        self::assertSame(
            'SELECT users.id FROM public.users JOIN roles ON roles.id = users.role_id',
            $definition->query,
        );
        self::assertSame(['users', 'roles'], $definition->dependencies);
        self::assertSame(
            'SELECT users.id FROM users JOIN roles ON roles.id = users.role_id',
            $definition->shadowQuery(['users', 'roles']),
        );
    }

    public function testRejectsCreateStatementWithoutViewQuery(): void
    {
        self::assertNull(ViewDefinition::fromCreateStatement('CREATE VIEW invalid'));
        self::assertNull(ViewDefinition::fromCreateStatement('CREATE VIEW invalid AS   '));
    }
}
