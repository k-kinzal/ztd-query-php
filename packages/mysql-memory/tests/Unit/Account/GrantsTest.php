<?php

declare(strict_types=1);

namespace Tests\Unit\Account;

use MySqlMemory\Account\Grants;
use MySqlMemory\Account\Identity;
use MySqlMemory\Account\Privileges;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Grants::class)]
#[Small]
final class GrantsTest extends TestCase
{
    public function testDatabaseKeepsTheLevel(): void
    {
        $grants = new Grants();
        $grants->database('d')->add(['SELECT']);

        self::assertSame(['SELECT' => true], $grants->databases['d']->names);
    }

    public function testTableKeepsTheLevel(): void
    {
        $grants = new Grants();
        $grants->table('d', 't')->add(['INSERT']);

        self::assertSame(['INSERT' => true], $grants->findTable('d', 't')?->names);
    }

    public function testFindTableAnswersNullForATableWithoutGrants(): void
    {
        self::assertNull((new Grants())->findTable('d', 't'));
    }

    public function testRoutineKeepsTheLevelUnderItsLowerCaseName(): void
    {
        $grants = new Grants();
        $grants->routine('PROCEDURE', 'd', 'Pr')->add(['EXECUTE']);

        self::assertSame(['PROCEDURE', 'd', 'pr'], array_slice(array_values($grants->routines)[0], 0, 3));
    }

    public function testFindRoutineComparesTheNameInLowerCase(): void
    {
        $grants = new Grants();
        $grants->routine('FUNCTION', 'd', 'f')->add(['EXECUTE']);

        self::assertSame(['EXECUTE' => true], $grants->findRoutine('FUNCTION', 'd', 'F')?->names);
    }

    public function testPruneDropsTheLevelsThatHoldNothing(): void
    {
        $grants = new Grants();
        $grants->database('d');
        $grants->table('d', 't')->grantOption = true;
        $grants->prune();

        self::assertSame([[], 1], [$grants->databases, count($grants->tables)]);
    }

    public function testClearRevokesEverything(): void
    {
        $grants = new Grants(new Privileges(['SELECT' => true]), ['BACKUP_ADMIN' => true]);
        $grants->database('d')->add(['SELECT']);
        $grants->clear();

        self::assertSame([[], [], []], [$grants->global->names, $grants->dynamic, $grants->databases]);
    }

    public function testClearPreservesProxyGrants(): void
    {
        $identity = new Identity('u', '%');
        $grants = new Grants(proxies: [$identity->key() => [$identity, true]]);
        $grants->clear();

        self::assertSame([$identity->key() => [$identity, true]], $grants->proxies);
    }

    public function testCopyChangesApart(): void
    {
        $grants = new Grants();
        $grants->table('d', 't')->add(['SELECT']);
        $copy = $grants->copy();
        $copy->table('d', 't')->add(['INSERT']);

        self::assertSame(['SELECT' => true], $grants->findTable('d', 't')?->names);
    }

    public function testMergeAddsEveryLevel(): void
    {
        $grants = new Grants(new Privileges(), ['BACKUP_ADMIN' => false]);
        $other = new Grants(new Privileges(['EVENT' => true]), ['BACKUP_ADMIN' => true], [], [], [(new Identity('', ''))->key() => [new Identity('', ''), true]]);
        $other->routine('PROCEDURE', 'd', 'p')->add(['EXECUTE']);
        $grants->merge($other);

        self::assertSame([['EVENT' => true], ['BACKUP_ADMIN' => true], 1, 1], [$grants->global->names, $grants->dynamic, count($grants->proxies), count($grants->routines)]);
    }
}
