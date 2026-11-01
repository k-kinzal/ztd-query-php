<?php

declare(strict_types=1);

namespace Tests\Unit\Account;

use MySqlMemory\Account\Privileges;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Privileges::class)]
#[Small]
final class PrivilegesTest extends TestCase
{
    public function testAddGrantsPrivileges(): void
    {
        $privileges = new Privileges();
        $privileges->add(['SELECT', 'INSERT']);

        self::assertSame(['SELECT' => true, 'INSERT' => true], $privileges->names);
    }

    public function testAddColumnsGrantsAPrivilegeOnColumns(): void
    {
        $privileges = new Privileges();
        $privileges->addColumns('SELECT', ['b', 'a']);

        self::assertSame(['a', 'b'], $privileges->columnsOf('SELECT'));
    }

    public function testRemoveRevokesThePrivilegeOnEveryColumnToo(): void
    {
        $privileges = new Privileges(['SELECT' => true]);
        $privileges->addColumns('SELECT', ['a']);
        $privileges->addColumns('INSERT', ['b']);
        $privileges->remove(['SELECT']);

        self::assertSame([[], ['b' => ['INSERT' => true]]], [$privileges->names, $privileges->columns]);
    }

    public function testRemoveColumnsRevokesAPrivilegeOnColumns(): void
    {
        $privileges = new Privileges();
        $privileges->addColumns('SELECT', ['a', 'b']);
        $privileges->removeColumns('SELECT', ['a']);

        self::assertSame(['b'], $privileges->columnsOf('SELECT'));
    }

    public function testEmptyTellsWhetherNothingIsHeld(): void
    {
        self::assertTrue((new Privileges())->empty());
        self::assertFalse((new Privileges([], true))->empty());
    }

    public function testColumnsOfAnswersTheColumnsInNameOrder(): void
    {
        self::assertSame(['a', 'c'], (new Privileges([], false, ['c' => ['SELECT' => true], 'a' => ['SELECT' => true], 'b' => ['INSERT' => true]]))->columnsOf('SELECT'));
    }

    public function testMergeAddsTheOtherLevel(): void
    {
        $privileges = new Privileges(['SELECT' => true]);
        $privileges->merge(new Privileges(['INSERT' => true], true, ['a' => ['UPDATE' => true]]));

        self::assertSame([['SELECT' => true, 'INSERT' => true], true, ['a' => ['UPDATE' => true]]], [$privileges->names, $privileges->grantOption, $privileges->columns]);
    }
}
