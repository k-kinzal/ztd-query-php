<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\Generators;
use Eris\TestTrait;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Prepared\PreparedStatement;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Account\RenameUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Account\RenameUserGenerator;
use Tests\Generator\MySql\Statement\Name\AccountNameGenerator;

/**
 * Generated account requests execute without SQL input or catalog-dependent construction.
 */
#[CoversNothing]
#[Large]
final class AccountRenameExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Ordinary SQL refusals leave the same balanced execution scope as successful requests.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesGeneratedRequestsExecuteWithoutSourceOrPriorAccounts(GrammarRelease $release): void
    {
        $this->forAll(new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, new RenameUserGenerator($release)))->withMaxSize(10)->then(static function (RenameUser $statement) use ($release): void {
            $session = (new Instance(substr($release->value, 6)))->connect();
            $session->text = 'Generated account request without SQL input';
            self::assertNull($statement->source);
            $execution = new Execution($session);
            try {
                $reply = $execution->statement($statement);
                self::assertInstanceOf(Completion::class, $reply);
                self::assertSame(0, $reply->affectedRows);
                self::assertNull($statement->problem);
            } catch (SqlError $error) {
                self::assertSame(1396, $error->getCode());
                $execution->failed($error);
                self::assertSame(-1, $session->variables->rowCount);
                self::assertGreaterThan(0, $session->diagnostics->errors());
            }
            self::assertSame('Generated account request without SQL input', $session->text);
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        });
    }

    /**
     * The generated first rename supplies the identity required by the prepared second one.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesDependentPreparedRenameRetainsItsIdentityWhileShrinking(GrammarRelease $release): void
    {
        $sequences = Generators::map(static function (AccountName $target) use ($release): array {
            $profile = new LanguageProfile($release);
            return array_map(static fn (\SqlSemantics\Platform\MySql\Statement\Statement $statement): \SqlSemantics\Platform\MySql\Statement\Statement => (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish(new \SqlSemantics\Contract\AnalysisContext($profile, [new Name('pbt')]), $statement), [new RenameUser([new UserRenaming(new CurrentUser(), $target)], $profile), new RenameUser([new UserRenaming($target, new AccountName(new Name('root'), new Name('%')))], $profile)]);
        }, new AccountNameGenerator());
        $this->forAll($sequences)->withMaxSize(10)->then(static function (array $sequence) use ($release): void {
            [$first, $second] = $sequence;
            self::assertInstanceOf(RenameUser::class, $first);
            self::assertInstanceOf(RenameUser::class, $second);
            $target = $first->renamings[0]->to;
            self::assertInstanceOf(AccountName::class, $target);
            self::assertEquals($target, $second->renamings[0]->from);
            $session = (new Instance(substr($release->value, 6)))->connect();
            $session->text = 'Generated dependent account sequence';
            $identity = new Identity($target->username(), $target->hostname() ?? '%');
            $original = $session->instance->accounts->find(new Identity('root', '%'));
            self::assertNotNull($original);
            $privileges = $original->grants;
            $prepared = PreparedStatement::capture($second, $session);
            self::assertArrayNotHasKey($identity->key(), $session->instance->accounts->accounts);
            $execution = new Execution($session);
            $reply = $execution->statement($first);
            self::assertInstanceOf(Completion::class, $reply);
            self::assertArrayNotHasKey((new Identity('root', '%'))->key(), $session->instance->accounts->accounts);
            self::assertSame($privileges, $session->instance->accounts->find($identity)?->grants);
            $reply = $prepared->execute($session, 'Generated dependent account sequence', []);
            self::assertInstanceOf(Completion::class, $reply);
            self::assertSame(0, $reply->affectedRows);
            self::assertNull($session->instance->accounts->find($identity));
            self::assertSame($privileges, $session->instance->accounts->find(new Identity('root', '%'))?->grants);
            self::assertSame('Generated dependent account sequence', $session->text);
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }
}
