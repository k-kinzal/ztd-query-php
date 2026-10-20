<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Audit;

use Fuzz\Structure\Audit\AccountHistory;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\RenameHistory;
use Fuzz\Structure\Execution;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Account histories supply initial state without reconstructing the target value.
 */
#[CoversNothing]
#[Medium]
final class RenameHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    /**
     * Restores initial occupancy and executes the exact original object again.
     */
    #[DataProvider('providerReleases')]
    public function testStepsEstablishStateBeforeRepeatingTheUnchangedChain(string $release): void
    {
        $account = static fn (string $name): M\Name\AccountName => new M\Name\AccountName(new Name($name));
        $node = new M\Account\RenameUser([new M\Account\User\UserRenaming($account('a'), $account('b')), new M\Account\User\UserRenaming($account('b'), $account('c'))]);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid input';
        $initial = (new Memory())->observe($session, $node);
        self::assertSame(1396, $initial['error'][0] ?? null);
        $history = array_map(static function (int $iteration) use ($node, $session): array {
            $steps = (new AccountHistory())->steps($node, 1396, $session);
            array_map(static fn (M\Statement $step) => (new Execution())->runIn($session, $step), $steps);
            return (new Memory())->observe($session, $node);
        }, [0, 1]);
        self::assertSame([false, false], array_map(static fn (array $outcome): bool => isset($outcome['error']), $history));
        self::assertSame('unrelated invalid input', $session->text);
    }

    /**
     * Reports the absence of a state witness under fixed legacy identity.
     */
    public function testRequirementsKeepCurrentAccountReleaseDifferencesVisible(): void
    {
        $node = new M\Account\RenameUser([
            new M\Account\User\UserRenaming(new M\Name\CurrentUser(), new M\Name\AccountName(new Name('a'))),
            new M\Account\User\UserRenaming(new M\Name\CurrentUser(), new M\Name\AccountName(new Name('b'))),
        ]);
        $legacy = (new Instance('5.7.44'))->connect();
        $modern = (new Instance('8.4.7'))->connect();
        self::assertNull((new RenameHistory())->requirements($node, $legacy));
        self::assertSame([], (new RenameHistory())->steps($node, $legacy));
        self::assertSame([true, false, false], array_column((new RenameHistory())->requirements($node, $modern) ?? [], 1));
    }

    /**
     * Creates the initial source only; a later pair reads an earlier destination.
     */
    public function testStepsDoNotCreateAnIntermediateDestination(): void
    {
        $account = static fn (string $name): M\Name\AccountName => new M\Name\AccountName(new Name($name));
        $node = new M\Account\RenameUser([new M\Account\User\UserRenaming($account('a'), $account('b')), new M\Account\User\UserRenaming($account('b'), $account('a'))]);
        $session = (new Instance())->connect();
        $steps = (new RenameHistory())->steps($node, $session);
        self::assertCount(1, $steps);
        self::assertInstanceOf(M\Account\CreateUser::class, $steps[0]);
        self::assertCount(1, $steps[0]->users);
        self::assertInstanceOf(M\Name\AccountName::class, $steps[0]->users[0]->user);
        self::assertSame('a', $steps[0]->users[0]->user->username());
    }
    /**
     * Observes a failed batch whose partial effect makes its unchanged retry valid.
     */
    public function testStepsNeedNoSetupWhenThePreviousFailureEstablishedTheState(): void
    {
        $node = new M\Account\RenameUser([
            new M\Account\User\UserRenaming(new M\Name\AccountName(new Name('a')), new M\Name\CurrentUser()),
            new M\Account\User\UserRenaming(new M\Name\CurrentUser(), new M\Name\AccountName(new Name('a'))),
        ]);
        $session = (new Instance('5.6.51'))->connect();
        $first = (new Memory())->observe($session, $node);
        self::assertSame(1396, $first['error'][0] ?? null);
        self::assertSame([], (new RenameHistory())->steps($node, $session));
        self::assertNotNull((new RenameHistory())->requirements($node, $session));
        self::assertArrayNotHasKey('error', (new Memory())->observe($session, $node));
    }

}
