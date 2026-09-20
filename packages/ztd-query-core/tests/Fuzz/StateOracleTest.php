<?php

declare(strict_types=1);

namespace Tests\Fuzz;

use Fuzz\Session\ReferenceState;
use Fuzz\Session\StateTarget;
use Fuzz\Session\VirtualSession;
use Fuzz\Shared\Oracle\Finding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * Deliberate state corruption must fail the core fuzz oracle.
 */
#[CoversNothing]
#[Medium]
final class StateOracleTest extends TestCase
{
    /**
     * Detect a row leaked into a session that never inserted it.
     */
    public function testUnexpectedRowsFail(): void
    {
        $actual = new VirtualSession();
        $actual->store->set('items', [['id' => 1, 'value' => 'leaked']]);
        $this->expectException(Finding::class);
        StateTarget::verify(new ReferenceState(), $actual);
    }

    /**
     * Detect a schema that was not restored with its rows.
     */
    public function testLostSchemaFails(): void
    {
        $actual = new VirtualSession();
        $actual->registry->unregister('items');
        $this->expectException(Finding::class);
        StateTarget::verify(new ReferenceState(), $actual);
    }

    /**
     * Detect unintended use of the physical connection.
     */
    public function testPhysicalQueryFails(): void
    {
        $actual = new VirtualSession();
        $actual->connection->query('DELETE FROM items');
        $this->expectException(Finding::class);
        StateTarget::verify(new ReferenceState(), $actual);
    }
}
