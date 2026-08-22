<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Postgres\PgSqlSelectRelationParser;
use ZtdQuery\Platform\Postgres\PgSqlViewDefinitionParser;
use ZtdQuery\Schema\ViewDefinition;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(PgSqlViewDefinitionParser::class)]
#[UsesClass(PgSqlSelectRelationParser::class)]
#[UsesClass(ViewDefinition::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(SqlTokenKind::class)]
#[UsesClass(SqlTokenStream::class)]
final class PgSqlViewDefinitionParserTest extends TestCase
{
    public function testParsesAQueryUsingPostgreSqlRelationRules(): void
    {
        $definition = (new PgSqlViewDefinitionParser())->fromQuery(
            " SELECT u.id FROM public.\"users\" u JOIN roles r ON r.id = u.role_id; \n",
        );

        self::assertSame('SELECT u.id FROM public."users" u JOIN roles r ON r.id = u.role_id', $definition->query);
        self::assertSame(['users', 'roles'], $definition->dependencies);
    }

    public function testExtractsTheQueryFromAPostgreSqlCreateViewStatement(): void
    {
        $definition = (new PgSqlViewDefinitionParser())->fromCreateStatement(
            'CREATE OR REPLACE VIEW "active_users" AS SELECT * FROM "users" WHERE active = TRUE;',
        );

        self::assertNotNull($definition);
        self::assertSame('SELECT * FROM "users" WHERE active = TRUE', $definition->query);
        self::assertNull((new PgSqlViewDefinitionParser())->fromCreateStatement('CREATE VIEW invalid AS   '));
    }
}
