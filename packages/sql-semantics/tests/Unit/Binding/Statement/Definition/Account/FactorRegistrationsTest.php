<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Account\FactorRegistrations;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor;
use SqlSemantics\Model\Definition\Account\Alteration\FactorChange;
use SqlSemantics\Model\Definition\Account\Alteration\FactorOperation;
use SqlSemantics\Model\Definition\Account\Alteration\FactorRemoval;
use SqlSemantics\Model\Statement\Definition\MySql\Account\FinishRegistrationStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Account\InitiateRegistrationStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Account\UnregisterFactorStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(FactorRegistrations::class)]
#[Medium]
final class FactorRegistrationsTest extends TestCase
{
    #[TestWith(['mysql-8.0.44', 'ALTER USER a 2 FACTOR INITIATE REGISTRATION', InitiateRegistrationStatement::class])]
    #[TestWith(['mysql-8.4.7', 'ALTER USER USER() 3 FACTOR UNREGISTER', UnregisterFactorStatement::class])]
    #[TestWith(['mysql-9.1.0', "ALTER USER a 2 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS 'r'", FinishRegistrationStatement::class])]
    public function testBindSelectsTheRegistrationStep(string $version, string $sql, string $class): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertSame($class, $statement::class);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testBindKeepsTheConnectingClientAccount(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER USER() 2 FACTOR UNREGISTER');
        self::assertInstanceOf(UnregisterFactorStatement::class, $statement);
        self::assertSame(ClientAccount::Connected, $statement->account);
        self::assertSame('ALTER USER USER() 2 FACTOR UNREGISTER', $statement->toString());
    }

    public function testChangePairsFactorsWithTheirIdentifications(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse("ALTER USER a MODIFY 3 FACTOR IDENTIFIED BY 'x' MODIFY 2 FACTOR IDENTIFIED WITH p");
        $change = FactorRegistrations::change($tree->find('alter_user')[0], new AccountName('a'), 'MODIFY', new Identifiers(Dialect::MySql));
        self::assertInstanceOf(FactorChange::class, $change);
        self::assertSame(FactorOperation::Modify, $change->operation);
        self::assertSame([AuthenticationFactor::Third, AuthenticationFactor::Second], array_column($change->factors, 'factor'));
    }

    public function testChangeReadsDroppedFactors(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER USER a DROP 3 FACTOR');
        self::assertEquals(new FactorRemoval(new AccountName('a'), [AuthenticationFactor::Third]), FactorRegistrations::change($tree->find('alter_user')[0], new AccountName('a'), 'DROP', new Identifiers(Dialect::MySql)));
    }

    #[TestWith(['ALTER USER a ADD 3 FACTOR IDENTIFIED WITH p ADD 2 FACTOR IDENTIFIED WITH q'])]
    #[TestWith(['ALTER USER a DROP 2 FACTOR DROP 2 FACTOR'])]
    public function testChangeRejectsImpossibleFactorCombinations(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::AuthenticationFactor->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
    }

    public function testFactorAcceptsLeadingZeros(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER USER a 002 FACTOR UNREGISTER');
        self::assertSame(AuthenticationFactor::Second, FactorRegistrations::factor($tree->find('factor')[0]));
    }

    #[TestWith(['1'])]
    #[TestWith(['4'])]
    public function testFactorRejectsNumbersOutsideTheSecondAndThird(string $number): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::AuthenticationFactor->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a ' . $number . ' FACTOR INITIATE REGISTRATION');
    }
}
