<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Foreign\MappingPrincipal;
use SqlSemantics\Model\Definition\Foreign\SetForeignOption;
use SqlSemantics\Model\Definition\Foreign\UserMappingIdentity;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\PostgreSql\AlterUserMappingStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterUserMappingStatement::class)]
#[Medium]
final class AlterUserMappingStatementTest extends TestCase
{
    public function testWithTargetReplacesTheUserAndServerTogether(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER MAPPING FOR USER SERVER remote OPTIONS (DROP old)');
        self::assertInstanceOf(AlterUserMappingStatement::class, $statement);
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
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER MAPPING FOR USER SERVER remote OPTIONS (DROP old)');
        self::assertInstanceOf(AlterUserMappingStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->target, $copy->target);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER MAPPING FOR USER SERVER remote OPTIONS (DROP old)');
        self::assertInstanceOf(AlterUserMappingStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithOptionsReplacesTheOrderedOperationList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER MAPPING FOR USER SERVER remote OPTIONS (DROP old)');
        self::assertInstanceOf(AlterUserMappingStatement::class, $statement);
        $value = Expression::literal('remote_user', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $changed = $statement->withOptions([new SetForeignOption(new ForeignOption('user', $value)), new DropForeignOption('password')]);
        self::assertInstanceOf(DropForeignOption::class, $statement->options[0]);
        self::assertSame('old', $statement->options[0]->name);
        self::assertInstanceOf(SetForeignOption::class, $changed->options[0]);
        self::assertInstanceOf(DropForeignOption::class, $changed->options[1]);
        self::assertSame('user', $changed->options[0]->option->name);
        self::assertSame('password', $changed->options[1]->name);
    }

    public function testWithOptionsRejectsAnEmptyModification(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER USER MAPPING FOR USER SERVER remote OPTIONS (DROP old)');
        self::assertInstanceOf(AlterUserMappingStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([]);
    }

    #[TestWith(['ALTER USER MAPPING FOR u SERVER s OPTIONS (ADD a \'x\', SET b \'y\', DROP c)', AlterUserMappingStatement::class, 'ALTER USER MAPPING FOR "u" SERVER "s" OPTIONS(ADD "a" \'x\', SET "b" \'y\', DROP "c")'])]
    public function testOptionsAcceptEveryChangeForm(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, $statement->toString()]);
    }
}
