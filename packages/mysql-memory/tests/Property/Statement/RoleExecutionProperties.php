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
use SqlSemantics\Platform\MySql\Statement\Account\CreateRole;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Account\CreateRoleGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\GrantRolesGenerator;
use Tests\Generator\MySql\Statement\Name\AccountNameGenerator;

/**
 * Generated role requests use the same execution boundary as analyzed requests.
 */
#[CoversNothing]
#[Large]
final class RoleExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * A fresh catalog either installs the complete generated batch or retains no additions.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesCreationIsAtomicWithoutPriorAccounts(GrammarRelease $release): void
    {
        $this->forAll(new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, new CreateRoleGenerator($release)))->withMaxSize(10)->then(static function (CreateRole $statement) use ($release): void {
            $session = (new Instance(substr($release->value, 6)))->connect();
            $session->text = 'Generated role creation without SQL input';
            $accounts = array_keys($session->instance->accounts->accounts);
            self::assertNull($statement->source);
            $execution = new Execution($session);
            try {
                $reply = $execution->statement($statement);
                self::assertInstanceOf(Completion::class, $reply);
                self::assertSame(0, $reply->affectedRows);
                self::assertNull($statement->problem);
                foreach ($statement->roles as $role) {
                    $account = $session->instance->accounts->find(new Identity($role->username(), $role->hostname() ?? '%'));
                    self::assertNotNull($account);
                    self::assertTrue($account->locked);
                }
            } catch (SqlError $error) {
                self::assertSame(1396, $error->getCode());
                self::assertTrue($statement->problem !== null || array_filter($statement->roles, static fn (AccountName $role): bool => $role->username() === '') !== []);
                $execution->failed($error);
                self::assertSame($accounts, array_keys($session->instance->accounts->accounts));
                self::assertSame(-1, $session->variables->rowCount);
            }
            self::assertSame('Generated role creation without SQL input', $session->text);
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        });
    }

    /**
     * Missing generated accounts produce only the applicable SQL errors.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesAbsentRoleStateIsAnOrdinarySqlRefusal(GrammarRelease $release): void
    {
        $this->forAll(new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, new GrantRolesGenerator($release)))->withMaxSize(10)->then(static function (GrantRoles $statement) use ($release): void {
            $session = (new Instance(substr($release->value, 6)))->connect();
            $session->text = 'Generated role grant without SQL input';
            $accounts = array_keys($session->instance->accounts->accounts);
            self::assertNull($statement->source);
            $execution = new Execution($session);
            try {
                $execution->statement($statement);
                self::fail('A generated non-root role cannot exist in a fresh catalog.');
            } catch (SqlError $error) {
                self::assertContains($error->getCode(), [3523, 3524]);
                $execution->failed($error);
                self::assertSame(-1, $session->variables->rowCount);
            }
            self::assertSame($accounts, array_keys($session->instance->accounts->accounts));
            self::assertSame([], $session->instance->accounts->roles(new Identity('root', '%')));
            self::assertSame('Generated role grant without SQL input', $session->text);
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        });
    }

    /**
     * Generated prerequisites and dependent grants retain their identities through shrinking.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesGeneratedDefinitionsSupplyPreparedRoleEdgesWhileShrinking(GrammarRelease $release): void
    {
        $sequences = Generators::map(static function (array $parts) use ($release): array {
            [$name, $admin] = $parts;
            $profile = new LanguageProfile($release);
            $role = new AccountName(new Name('r' . $name->username()), $name->host);
            $recipient = new AccountName(new Name('g' . $name->username()), $name->host);
            return array_map(static fn (\SqlSemantics\Platform\MySql\Statement\Statement $statement): \SqlSemantics\Platform\MySql\Statement\Statement => (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish(new \SqlSemantics\Contract\AnalysisContext($profile, [new Name('pbt')]), $statement), [
                new CreateRole(true, [$role, $recipient], $profile),
                new GrantRoles([new GrantedRole($role)], [$recipient], $admin, $profile),
                new GrantRoles([new GrantedRole($recipient)], [$role], false, $profile),
                new GrantRoles([new GrantedRole($role)], [$recipient], false, $profile),
            ]);
        }, \Tests\Generator\Operands::tuple(new AccountNameGenerator(), Generators::bool()));
        $this->forAll($sequences)->withMaxSize(10)->then(static function (array $sequence) use ($release): void {
            [$definition, $grant, $cycle, $repeated] = $sequence;
            self::assertInstanceOf(CreateRole::class, $definition);
            self::assertInstanceOf(GrantRoles::class, $grant);
            self::assertInstanceOf(GrantRoles::class, $cycle);
            self::assertInstanceOf(GrantRoles::class, $repeated);
            self::assertEquals($definition->roles[0], $grant->roles[0]->role);
            self::assertEquals($definition->roles[1], $grant->users[0]);
            $role = new Identity($definition->roles[0]->username(), $definition->roles[0]->hostname() ?? '%');
            $recipient = new Identity($definition->roles[1]->username(), $definition->roles[1]->hostname() ?? '%');
            $session = (new Instance(substr($release->value, 6)))->connect();
            $session->text = 'Generated dependent role sequence';
            $prepared = PreparedStatement::capture($grant, $session);
            self::assertNull($session->instance->accounts->find($role));
            self::assertNull($session->instance->accounts->find($recipient));
            $execution = new Execution($session);
            self::assertInstanceOf(Completion::class, $execution->statement($definition));
            self::assertInstanceOf(Completion::class, $prepared->execute($session, 'Generated dependent role sequence', []));
            $edges = $session->instance->accounts->roles($recipient);
            self::assertSame([$role->key()], array_keys($edges));
            self::assertSame($grant->withAdminOption, $edges[$role->key()][1]);
            try {
                $execution->statement($cycle);
                self::fail('The generated inverse edge must be refused.');
            } catch (SqlError $error) {
                self::assertSame(4027, $error->getCode());
                $execution->failed($error);
            }
            self::assertSame([], $session->instance->accounts->roles($role));
            self::assertInstanceOf(Completion::class, $execution->statement($repeated));
            self::assertEquals($edges, $session->instance->accounts->roles($recipient));
            self::assertSame(0, $execution->statement($definition)->affectedRows);
            self::assertSame([3163, 3163], array_column($session->diagnostics->conditions, 1));
            self::assertEquals($edges, $session->instance->accounts->roles($recipient));
            self::assertSame('Generated dependent role sequence', $session->text);
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
            if ($release->database() === 'mysql' && !in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                yield $release->value => [$release];
            }
        }
    }
}
