<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\MySqlSelectRelationParser;
use ZtdQuery\Platform\MySql\MySqlViewDefinitionParser;

#[CoversClass(MySqlViewDefinitionParser::class)]
#[UsesClass(MySqlLexerProfile::class)]
#[UsesClass(MySqlSelectRelationParser::class)]
final class MySqlViewDefinitionParserTest extends TestCase
{
    public function testParsesAQueryUsingMySqlRelationRules(): void
    {
        $definition = (new MySqlViewDefinitionParser())->fromQuery(
            " SELECT u.id FROM app.`users` u JOIN roles r ON r.id = u.role_id; \n",
        );

        self::assertSame('SELECT u.id FROM app.`users` u JOIN roles r ON r.id = u.role_id', $definition->query);
        self::assertSame(['users', 'roles'], $definition->dependencies);
    }

    public function testExtractsTheQueryFromAMySqlCreateViewStatement(): void
    {
        $definition = (new MySqlViewDefinitionParser())->fromCreateStatement(
            'CREATE ALGORITHM=MERGE VIEW `active_users` AS SELECT * FROM `users` WHERE active = 1;',
        );

        self::assertNotNull($definition);
        self::assertSame('SELECT * FROM `users` WHERE active = 1', $definition->query);
        self::assertNull((new MySqlViewDefinitionParser())->fromCreateStatement('CREATE VIEW invalid AS   '));
    }
}
