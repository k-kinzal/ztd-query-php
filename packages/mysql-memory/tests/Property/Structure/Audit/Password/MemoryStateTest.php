<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Audit\Password;

use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Password\Contract;
use Fuzz\Structure\Audit\Password\MemoryState;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Password;
use SqlSemantics\Platform\MySql\Statement\Account\SetPassword;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * A random-password exception requires real replacement and retention relationships.
 */
#[CoversNothing]
#[Medium]
final class MemoryStateTest extends TestCase
{
    /**
     * Refuses retention without an existing primary and permits an ordinary request's baseline.
     */
    public function testCaptureRequiresAPreviousPrimaryForRetention(): void
    {
        $session = (new Instance())->connect();
        $state = new MemoryState();

        self::assertNull($state->capture($session, true));
        self::assertNotNull($state->capture($session, false));
    }

    /**
     * Rejects an invented returned password and a missing secondary, even with a valid result shape.
     */
    public function testVerifiesRejectsWrongPasswordsAndBrokenRetention(): void
    {
        $session = (new Instance())->connect();
        $observer = new Memory();
        $observer->observe($session, new SetPassword(null, new Password(new Text('original'))));
        $state = new MemoryState();
        $policy = $state->capture($session, true);
        self::assertNotNull($policy);
        $result = $observer->observe($session, new SetPassword(null, null, retainCurrent: true));
        $password = $policy->password(Contract::project($result));
        self::assertNotNull($password);

        self::assertFalse($state->verifies($session, $policy, $password . '!'));
        $account = $session->instance->accounts->find($session->variables->definer);
        self::assertNotNull($account);
        $account->secondary = null;
        self::assertFalse($state->verifies($session, $policy, $password));
    }

    /**
     * Checks successful replacement and discard using this instance's actual generated credential.
     */
    public function testVerifiesPreservesTheNewPrimaryAndDiscardsTheOldCredential(): void
    {
        $session = (new Instance())->connect();
        $observer = new Memory();
        $observer->observe($session, new SetPassword(null, new Password(new Text('original'))));
        $state = new MemoryState();
        $policy = $state->capture($session, true);
        self::assertNotNull($policy);
        $result = $observer->observe($session, new SetPassword(null, null, retainCurrent: true));
        $password = $policy->password(Contract::project($result));
        self::assertNotNull($password);

        self::assertTrue($state->verifies($session, $policy, $password));
        self::assertNull($session->instance->accounts->find($session->variables->definer)?->secondary);
    }

    /**
     * Refuses incomplete responses and unexpected effects during password validation.
     */
    public function testCompletedRequiresTheExactSuccessfulResponse(): void
    {
        $state = new MemoryState();
        $success = ['results' => [['affected' => 0, 'lastInsertId' => '0']], 'warnings' => [], 'disconnected' => false];

        self::assertTrue($state->completed($success));
        self::assertFalse($state->completed($success + ['error' => null]));
        self::assertFalse($state->completed(['results' => [['affected' => 1, 'lastInsertId' => '0']], 'warnings' => [], 'disconnected' => false]));
        self::assertFalse($state->completed(['results' => [['affected' => 0, 'lastInsertId' => '0']], 'warnings' => [], 'disconnected' => true]));
        self::assertFalse($state->completed(['results' => [['affected' => 0, 'lastInsertId' => '0']], 'disconnected' => false]));
    }

}
