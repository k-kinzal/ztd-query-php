<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Account;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\AlterUser;
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\Factor;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorAction;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorChange;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorStep;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Choices;

/**
 * Valid factor structures reach state-dependent account/plugin checks.
 * This does not classify arbitrary SQL rejections as successful trials.
 */
#[CoversNothing]
#[Medium]
final class AuthenticationFactorProperties extends TestCase
{
    /**
     * Reuses CURRENT_USER unchanged across removal and recreation of its account.
     */
    #[DataProvider('providerHistories')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesCurrentUserRetainsPrimaryAuthenticationAcrossAnAccountTransition(string $release, int $seed): void
    {
        $choices = new Choices('current-user-factor:' . $seed);
        $account = new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser($choices->one(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::cases()));
        $method = static fn () => new \SqlSemantics\Platform\MySql\Statement\Account\User\Identification(null, \SqlSemantics\Platform\MySql\Statement\Account\User\Credential::Password, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('password_' . $choices->integer(0, 100000)));
        $factors = array_map(static fn () => $method(), range(1, $choices->integer(1, 2)));
        $statement = new CreateUser(false, [new UserSpecification($account, $method(), $factors)]);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid source';
        $execution = new Execution();
        try {
            $execution->runIn($session, $statement);
            self::fail('The connected account already exists.');
        } catch (SqlError $failure) {
            self::assertSame(1396, $failure->getCode());
            self::assertSame([], $session->diagnostics->conditions);
        }
        $execution->runIn($session, new \SqlSemantics\Platform\MySql\Statement\Account\DropUser(false, [$account]));
        $reply = $execution->runIn($session, $statement);
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
        self::assertSame(0, $reply->warnings());
        self::assertNotNull($session->instance->accounts->find(new \MySqlMemory\Account\Identity($session->user, $session->host)));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Reuses a generated factor change before and after its account is created.
     */
    #[DataProvider('providerHistories')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesTypedFactorsDistinguishMissingAccountsFromMissingFactors(string $release, int $seed): void
    {
        $choices = new Choices('authentication-factor:' . $seed);
        $factor = $choices->one(Factor::cases());
        $account = new AccountName(new Name('factor_user_' . $choices->integer(0, 100000)));
        $statement = new AlterUser(false, [new FactorChange($account, FactorAction::Drop, [new FactorStep($factor)])]);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid source';
        $execution = new Execution();
        $errors = [];
        foreach ([false, true] as $exists) {
            if ($exists) {
                $execution->runIn($session, new CreateUser(false, [new UserSpecification($account)]));
            }
            try {
                $execution->runIn($session, $statement);
                self::fail('No installed additional authentication factor exists in this fixture.');
            } catch (SqlError $failure) {
                $errors[] = [$failure->getCode(), $failure->getMessage()];
            }
        }

        self::assertSame([[1524, "Plugin '' is not loaded"], [4057, $factor->value . " factor authentication method doesn't exist. Please do ALTER USER... ADD " . $factor->value . ' factor... before doing this operation.']], $errors);
    }
}
