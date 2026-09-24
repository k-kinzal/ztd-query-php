<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Account\Alteration\AccountTarget;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor;
use SqlSemantics\Model\Definition\Account\Alteration\FactorChange;
use SqlSemantics\Model\Definition\Account\Alteration\FactorIdentification;
use SqlSemantics\Model\Definition\Account\Alteration\FactorOperation;
use SqlSemantics\Model\Definition\Account\GeneratedPasswordRows;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginRandomPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Definition\Account\InitialAuthenticationDefinition;
use SqlSemantics\SchemaBuilder;

#[CoversClass(GeneratedPasswordRows::class)]
#[Medium]
final class GeneratedPasswordRowsTest extends TestCase
{
    public function testDefinitionDetectsAGeneratedAdditionalFactor(): void
    {
        self::assertTrue(GeneratedPasswordRows::definition(new AccountDefinition(new AccountName('u'), new PluginIdentification('p'), [new PluginRandomPasswordIdentification('q')])));
        self::assertFalse(GeneratedPasswordRows::definition(new AccountDefinition(new AccountName('u'), new PluginIdentification('p'))));
    }

    public function testDefinitionDetectsAGeneratedInitialCredential(): void
    {
        self::assertTrue(GeneratedPasswordRows::definition(new InitialAuthenticationDefinition(new AccountName('u'), 'authentication_fido', RandomPassword::Generated)));
    }

    public function testAlterationDetectsAGeneratedFactor(): void
    {
        self::assertTrue(GeneratedPasswordRows::alteration(new FactorChange(new AccountName('u'), FactorOperation::Add, [new FactorIdentification(AuthenticationFactor::Second, RandomPassword::Generated)])));
        self::assertFalse(GeneratedPasswordRows::alteration(new FactorChange(new AccountName('u'), FactorOperation::Add, [new FactorIdentification(AuthenticationFactor::Second, new PluginIdentification('p'))])));
        self::assertFalse(GeneratedPasswordRows::alteration(new AccountTarget(new AccountName('u'))));
    }

    public function testColumnsListTheGeneratedPasswordFields(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        $columns = GeneratedPasswordRows::columns($statement->source, $statement->scopeId, new AccountName('u'));
        self::assertSame(['user', 'host', 'generated password', 'auth_factor'], array_column($columns, 'name'));
        self::assertSame([], GeneratedPasswordRows::columns($statement->source, $statement->scopeId, null));
    }

    public function testDefinitionDetectsAGeneratedFirstFactor(): void
    {
        self::assertTrue(GeneratedPasswordRows::definition(new AccountDefinition(new AccountName('u'), RandomPassword::Generated)));
        self::assertTrue(GeneratedPasswordRows::definition(new AccountDefinition(new AccountName('u'), new PluginRandomPasswordIdentification('q'))));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['ALTER USER u IDENTIFIED BY RANDOM PASSWORD', true])]
    #[\PHPUnit\Framework\Attributes\TestWith(['ALTER USER u IDENTIFIED WITH caching_sha2_password BY RANDOM PASSWORD', true])]
    #[\PHPUnit\Framework\Attributes\TestWith(["ALTER USER u IDENTIFIED BY 'x'", false])]
    #[\PHPUnit\Framework\Attributes\TestWith(["ALTER USER u IDENTIFIED WITH caching_sha2_password BY 'x'", false])]
    public function testAlterationDetectsAGeneratedCredentialChange(string $sql, bool $generated): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\MySql\Account\AlterUsersStatement::class, $statement);
        self::assertSame($generated, GeneratedPasswordRows::alteration($statement->alterations[0]));
    }
}
