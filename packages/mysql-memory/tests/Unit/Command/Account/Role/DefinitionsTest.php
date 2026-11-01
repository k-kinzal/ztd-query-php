<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Account\Role;

use MySqlMemory\Command\Account\Role\Definitions;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(Definitions::class)]
#[Medium]
final class DefinitionsTest extends TestCase
{
    public function testExecuteKeepsTheAnonymousConditionalDefinitionDependentOnExistingAccounts(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE USER ''");
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $session->query("CREATE ROLE IF NOT EXISTS ''")[0]);
        self::assertSame([['Note', 3163, "Authorization ID ''@'%' already exists."]], $session->diagnostics->conditions);
    }

    public function testResolveRetainsEarlierNotesBeforeAnAnonymousCreationFails(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE ROLE r');
        $error = $session->run("CREATE ROLE IF NOT EXISTS r, ''")[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $error);
        self::assertSame(1396, $error->getCode());
        self::assertSame([['Note', 3163, "Authorization ID 'r'@'%' already exists."], ['Error', 1396, 'Operation CREATE ROLE failed for anonymous user']], $session->diagnostics->conditions);
    }

    public function testCreatePublishesTheCompleteNewAccountBatch(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE ROLE r, s');
        $roles = array_map(static fn (string $sql) => $session->query($sql)[0], ['SHOW GRANTS FOR r', 'SHOW GRANTS FOR s']);
        self::assertContainsOnlyInstancesOf(\MySqlMemory\Result\ResultSet::class, $roles);
    }

    public function testPluginRetainsAStoredConditionalSkipAcrossAccountDeletion(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE PROCEDURE p() CREATE ROLE IF NOT EXISTS r; CREATE ROLE r; CALL p(); DROP ROLE r');
        $error = $session->run('CALL p()')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $error);
        self::assertSame([1524, "Plugin '' is not loaded"], [$error->getCode(), $error->getMessage()]);
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $session->query('CREATE ROLE IF NOT EXISTS r')[0]);
    }
}
