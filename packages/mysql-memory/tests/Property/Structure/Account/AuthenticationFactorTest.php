<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Account;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
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

/**
 * Valid factor structures reach state-dependent account/plugin checks.
 * This does not classify arbitrary SQL rejections as successful trials.
 */
#[CoversNothing]
#[Medium]
final class AuthenticationFactorTest extends TestCase
{
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
    public function testTypedFactorsDistinguishMissingAccountsFromMissingFactors(string $release, int $seed): void
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
