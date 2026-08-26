<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\NotNullViolationException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\Mutation\RowConstraints;

#[CoversClass(RowConstraints::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(DuplicateKeyException::class)]
#[UsesClass(NotNullViolationException::class)]
final class RowConstraintsTest extends TestCase
{
    public function testAssertNoNullWhereNoneIsAllowedRefusesANullInSuchAColumn(): void
    {
        $constraints = new RowConstraints(
            new TableDefinition(['id', 'name'], [], ['id'], ['name'], []),
            'users',
            'INSERT INTO users VALUES (1, NULL)',
        );

        $this->expectException(NotNullViolationException::class);

        $constraints->assertNoNullWhereNoneIsAllowed(['id' => 1, 'name' => null]);
    }

    public function testAssertNoNullWhereNoneIsAllowedLetsAColumnTheRowNeverWroteThrough(): void
    {
        $constraints = new RowConstraints(
            new TableDefinition(['id', 'name'], [], ['id'], ['name'], []),
            'users',
            'INSERT INTO users (id) VALUES (1)',
        );

        $this->expectNotToPerformAssertions();

        $constraints->assertNoNullWhereNoneIsAllowed(['id' => 1]);
    }

    public function testAssertNoNullWhereNoneIsAllowedLetsEverythingThroughForATableNothingDescribes(): void
    {
        $constraints = new RowConstraints(null, 'users', 'INSERT INTO users VALUES (NULL)');

        $this->expectNotToPerformAssertions();

        $constraints->assertNoNullWhereNoneIsAllowed(['name' => null]);
    }

    public function testAssertNoDuplicateUniqueKeyRefusesARowCollidingWithOneAlreadyThere(): void
    {
        $constraints = new RowConstraints(
            new TableDefinition(['id', 'email'], [], ['id'], [], ['email' => ['email']]),
            'users',
            'INSERT INTO users VALUES (2, "a@example.com")',
        );

        $this->expectException(DuplicateKeyException::class);

        $constraints->assertNoDuplicateUniqueKey(
            ['id' => 2, 'email' => 'a@example.com'],
            [['id' => 1, 'email' => 'a@example.com']],
        );
    }

    public function testAssertNoDuplicateUniqueKeyLetsARowNotCollideWithItself(): void
    {
        $constraints = new RowConstraints(
            new TableDefinition(['id', 'email'], [], ['id'], [], ['email' => ['email']]),
            'users',
            'UPDATE users SET email = "a@example.com" WHERE id = 1',
        );

        $this->expectNotToPerformAssertions();

        $constraints->assertNoDuplicateUniqueKey(
            ['id' => 1, 'email' => 'a@example.com'],
            [['id' => 1, 'email' => 'a@example.com']],
            ['id'],
        );
    }

    public function testAssertNoDuplicateUniqueKeyLetsTwoNullsThroughBecauseNeitherCollides(): void
    {
        $constraints = new RowConstraints(
            new TableDefinition(['id', 'email'], [], ['id'], [], ['email' => ['email']]),
            'users',
            'INSERT INTO users VALUES (2, NULL)',
        );

        $this->expectNotToPerformAssertions();

        $constraints->assertNoDuplicateUniqueKey(
            ['id' => 2, 'email' => null],
            [['id' => 1, 'email' => null]],
        );
    }

    public function testCarriesNullInReportsAColumnTheRowLeftNull(): void
    {
        $constraints = new RowConstraints(null, 'users', '');

        self::assertTrue($constraints->carriesNullIn(['email' => null], ['email']));
    }

    public function testCarriesNullInReportsAColumnTheRowNeverWrote(): void
    {
        $constraints = new RowConstraints(null, 'users', '');

        self::assertTrue($constraints->carriesNullIn(['id' => 1], ['email']));
    }

    public function testCarriesNullInIsFalseWhereEveryColumnCarriesSomething(): void
    {
        $constraints = new RowConstraints(null, 'users', '');

        self::assertFalse($constraints->carriesNullIn(['email' => 'a@example.com'], ['email']));
    }

    public function testAgreeOnReportsTwoRowsCarryingTheSameValuesThere(): void
    {
        $constraints = new RowConstraints(null, 'users', '');

        self::assertTrue($constraints->agreeOn(['id' => 1], ['id' => 1, 'name' => 'a'], ['id']));
    }

    public function testAgreeOnIsFalseWhereTheRowAlreadyThereCarriesNothing(): void
    {
        $constraints = new RowConstraints(null, 'users', '');

        self::assertFalse($constraints->agreeOn(['id' => 1], ['id' => null], ['id']));
    }

    public function testKeyValuesReadsWhatTheRowCarriesUnderTheColumnNames(): void
    {
        $constraints = new RowConstraints(null, 'users', '');

        self::assertSame(['id' => 1, 'email' => null], $constraints->keyValues(['id' => 1], ['id', 'email']));
    }
}
