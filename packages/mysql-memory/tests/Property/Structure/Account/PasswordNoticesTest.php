<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Account;

use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Choices;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Password;
use SqlSemantics\Platform\MySql\Statement\Account\PasswordFunction;
use SqlSemantics\Platform\MySql\Statement\Account\SetPassword;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Keeps a password request unchanged across absent accounts and both native legacy plugins.
 */
#[CoversNothing]
#[Medium]
final class PasswordNoticesTest extends TestCase
{
    /**
     * @return iterable<string, array{SetPassword}>
     */
    public static function providerRequests(): iterable
    {
        $choices = new Choices('password-recipient-20261017');
        for ($trial = 0; $trial < 40; $trial++) {
            yield (string) $trial => [new SetPassword(new AccountName(new Name('u'), new Name('localhost')), new Password(new Text((string) $choices->integer(0, 100000)), PasswordFunction::OldPassword))];
        }
    }

    /**
     * An error caused by the recipient's authentication format is not a scalar construction defect.
     */
    #[DataProvider('providerRequests')]
    public function testUnchangedPasswordFollowsTheRecipientPlugin(SetPassword $statement): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $memory = new Memory();
        $session->text = 'unrelated invalid input';
        $missing = $memory->observe($session, $statement);
        $session->query('CREATE USER u@localhost IDENTIFIED WITH mysql_old_password');
        $session->text = 'unrelated invalid input';
        $old = $memory->observe($session, $statement);
        $session->query("DROP USER u@localhost; CREATE USER u@localhost IDENTIFIED BY 'pw'");
        $session->text = 'unrelated invalid input';
        $modern = $memory->observe($session, $statement);
        self::assertSame(1133, $missing['error'][0] ?? null);
        self::assertArrayNotHasKey('error', $old);
        self::assertSame(['1287', '1287'], array_column($old['warnings'] ?? [], 1));
        self::assertSame(1372, $modern['error'][0] ?? null);
        self::assertSame(['1287', '1372'], array_column($modern['warnings'] ?? [], 1));
        self::assertSame('unrelated invalid input', $session->text);
    }
}
