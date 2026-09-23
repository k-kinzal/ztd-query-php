<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Foreign\MappingPrincipal;
use SqlSemantics\Model\Definition\Foreign\UserMappingIdentity;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\PostgreSql\CreateUserMappingStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateUserMappingStatement::class)]
#[Medium]
final class CreateUserMappingStatementTest extends TestCase
{
    public function testWithTargetReplacesTheUserAndServerTogether(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER MAPPING FOR USER SERVER remote');
        self::assertInstanceOf(CreateUserMappingStatement::class, $statement);
        $changed = $statement->withTarget(new UserMappingIdentity(new NamedRole('CURRENT_USER'), 'other"server'));
        self::assertSame(MappingPrincipal::CurrentUser, $statement->target->user);
        self::assertSame('remote', $statement->target->server);
        self::assertInstanceOf(NamedRole::class, $changed->target->user);
        self::assertSame('CURRENT_USER', $changed->target->user->name);
        self::assertSame('other"server', $changed->target->server);
        self::assertStringContainsString('FOR "CURRENT_USER" SERVER "other""server"', $changed->toString());
        self::assertNotSame($statement, $changed);
    }

    public function testWithOriginRetainsTheMappingIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER MAPPING FOR USER SERVER remote');
        self::assertInstanceOf(CreateUserMappingStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->target, $copy->target);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER MAPPING FOR USER SERVER remote');
        self::assertInstanceOf(CreateUserMappingStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithIfNotExistsChangesOnlyTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER MAPPING FOR USER SERVER remote');
        self::assertInstanceOf(CreateUserMappingStatement::class, $statement);
        $changed = $statement->withIfNotExists(true);
        self::assertFalse($statement->ifNotExists);
        self::assertTrue($changed->ifNotExists);
        self::assertSame($statement->target->server, $changed->target->server);
        self::assertSame($statement->target->user, $changed->target->user);
    }
    public function testWithOptionsReplacesInitialTextOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER MAPPING FOR USER SERVER remote');
        self::assertInstanceOf(CreateUserMappingStatement::class, $statement);
        $value = Expression::literal('remote_user', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $changed = $statement->withOptions([new ForeignOption('user', $value)]);
        self::assertSame([], $statement->options);
        self::assertSame('user', $changed->options[0]->name);
        self::assertSame($value->text, $changed->options[0]->value->text);
        self::assertSame([], $changed->withOptions([])->options);
    }

    public function testWithOptionsRejectsRepeatedInitialNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER MAPPING FOR USER SERVER remote');
        self::assertInstanceOf(CreateUserMappingStatement::class, $statement);
        $value = Expression::literal('remote_user', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([new ForeignOption('user', $value), new ForeignOption('user', $value)]);
    }

}
