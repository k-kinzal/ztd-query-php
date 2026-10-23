<?php

declare(strict_types=1);

namespace Tests\Unit\Registry;

use MySqlMemory\Registry\ResourceGroup;
use MySqlMemory\Registry\ResourceGroups;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourceGroups::class)]
#[Small]
final class ResourceGroupsTest extends TestCase
{
    public function testFindIgnoresLetterCase(): void
    {
        $group = (new ResourceGroups())->find('usr_DEFAULT');

        self::assertNotNull($group);
        self::assertSame(['USR_default', false, '0-7'], [$group->name, $group->system, $group->vcpus()]);
    }

    public function testFindAnswersNullForAMissingGroup(): void
    {
        self::assertNull((new ResourceGroups())->find('batch'));
    }

    public function testPredefinedTellsTheDefaultGroups(): void
    {
        $groups = new ResourceGroups();

        self::assertSame([true, true, false], [$groups->predefined('SYS_DEFAULT'), $groups->predefined('usr_default'), $groups->predefined('batch')]);
    }

    public function testAddKeepsAGroupByName(): void
    {
        $groups = new ResourceGroups();
        $groups->add(new ResourceGroup('Batch', false, [0]));

        self::assertSame('Batch', $groups->find('BATCH')?->name);
    }

    public function testRemoveMovesTheThreadsOfTheGroupBack(): void
    {
        $groups = new ResourceGroups();
        $groups->add(new ResourceGroup('batch', false, [0]));
        $groups->bind(7, 'batch');
        $groups->remove('Batch');

        self::assertSame([null, []], [$groups->find('batch'), $groups->bindings]);
    }

    public function testBusyTellsWhetherAThreadIsAssigned(): void
    {
        $groups = new ResourceGroups();
        $groups->add(new ResourceGroup('batch', false, [0]));
        $before = $groups->busy('batch');
        $groups->bind(7, 'BATCH');

        self::assertSame([false, true], [$before, $groups->busy('batch')]);
    }

    public function testBindAssignsTheThreadOfASession(): void
    {
        $groups = new ResourceGroups();
        $groups->bind(3, 'USR_default');

        self::assertSame([3], array_keys($groups->bindings));
    }
}
