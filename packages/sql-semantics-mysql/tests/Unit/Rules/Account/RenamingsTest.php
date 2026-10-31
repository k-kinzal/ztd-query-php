<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Account\Renamings;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Renamings::class)]
#[Small]
final class RenamingsTest extends TestCase
{
    /**
     * @return iterable<string, array{list<UserRenaming>, bool}>
     */
    public static function providerConstraints(): iterable
    {
        $cases = [
            'same account' => [[['a', 'a']], false],
            'same current account' => [[['@', '@']], false],
            'source reused' => [[['a', 'b'], ['a', 'c']], false],
            'destination reused' => [[['a', 'c'], ['b', 'c']], false],
            'empty source after cycle' => [[['a', 'b'], ['b', 'a'], ['b', 'c']], false],
            'chain' => [[['a', 'b'], ['b', 'c']], true],
            'cycle' => [[['a', 'b'], ['b', 'a']], true],
            'swap with temporary identity' => [[['a', 'c'], ['b', 'a'], ['c', 'b']], true],
            'following current identity' => [[['@', 'a'], ['@', 'b']], true],
            'fixed current identity' => [[['@', 'a'], ['a', '@']], true],
            'missing current account' => [[['a', '@'], ['@', 'b']], true],
        ];
        $account = static fn (string $name): AccountName|CurrentUser => $name === '@' ? new CurrentUser() : new AccountName(new Name($name));
        foreach ($cases as $label => [$pairs, $consistent]) {
            yield $label => [array_map(static fn (array $pair): UserRenaming => new UserRenaming($account($pair[0]), $account($pair[1])), $pairs), $consistent];
        }
    }

    /**
     * @param list<UserRenaming> $pairs The ordered identity constraints
     */
    #[DataProvider('providerConstraints')]
    public function testProblemDistinguishesContradictionsFromCatalogDependencies(array $pairs, bool $consistent): void
    {
        self::assertSame($consistent, (new Renamings())->problem($pairs) === null);
    }

    public function testConsistentPreservesBothCurrentAccountInterpretations(): void
    {
        $pairs = [new UserRenaming(new CurrentUser(), new AccountName(new Name('a'))), new UserRenaming(new CurrentUser(), new AccountName(new Name('b')))];
        $rules = new Renamings();
        self::assertFalse($rules->consistent($pairs, 'unmentioned', false));
        self::assertTrue($rules->consistent($pairs, 'unmentioned', true));
    }

    public function testIdentityUsesTheAccountNameComparisonRules(): void
    {
        $rules = new Renamings();
        self::assertSame($rules->identity(new AccountName(new Name(' a ')), ''), $rules->identity(new AccountName(new Name('a'), new Name(' % ')), ''));
        self::assertSame($rules->identity(new AccountName(new Name('a'), new Name('HOST')), ''), $rules->identity(new AccountName(new Name('a'), new Name('host')), ''));
        self::assertNotSame($rules->identity(new AccountName(new Name('a')), ''), $rules->identity(new AccountName(new Name('A')), ''));
        self::assertSame('current', $rules->identity(new CurrentUser(), 'current'));
    }
}
