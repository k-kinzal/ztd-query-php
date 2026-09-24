<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Binder;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\MySql\Account\FinishRegistrationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(FinishRegistrationStatement::class)]
#[Medium]
final class FinishRegistrationStatementTest extends TestCase
{
    public function testWithAccountReplacesTheAccountWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER USER a 2 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS 'r'");
        self::assertInstanceOf(FinishRegistrationStatement::class, $statement);
        $changed = $statement->withAccount(CurrentAccount::Authenticated);
        self::assertEquals(new AccountName('a'), $statement->account);
        self::assertSame("ALTER USER CURRENT_USER 2 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS 'r'", $changed->toString());
    }

    public function testWithFactorReplacesTheFactor(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER USER a 2 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS 'r'");
        self::assertInstanceOf(FinishRegistrationStatement::class, $statement);
        $changed = $statement->withFactor(AuthenticationFactor::Third);
        self::assertSame(AuthenticationFactor::Second, $statement->factor);
        self::assertSame("ALTER USER 'a' 3 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS 'r'", $changed->toString());
    }

    public function testWithChallengeResponseKeepsAHexadecimalSpelling(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER USER a 2 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS 'r'");
        self::assertInstanceOf(FinishRegistrationStatement::class, $statement);
        $response = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'HEX_NUM', '0x0A', 0));
        self::assertInstanceOf(Literal::class, $response);
        $changed = $statement->withChallengeResponse($response);
        self::assertSame("'r'", $statement->challengeResponse->text);
        self::assertSame("ALTER USER 'a' 2 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS 0x0A", $changed->toString());
    }

    public function testWithChallengeResponseRejectsADecimalNumber(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER USER a 2 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS 'r'");
        self::assertInstanceOf(FinishRegistrationStatement::class, $statement);
        $response = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'NUM', '10', 0));
        self::assertInstanceOf(Literal::class, $response);
        $this->expectException(InvalidStructure::class);
        $statement->withChallengeResponse($response);
    }

    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER USER a 2 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS 'r'");
        self::assertInstanceOf(FinishRegistrationStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->challengeResponse, $copy->challengeResponse);
    }
}
