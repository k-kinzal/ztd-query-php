<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\Key\ForeignKeyDefinition;
use ZtdQuery\Shadow\FollowedConstraint;

#[CoversClass(FollowedConstraint::class)]
#[UsesClass(ForeignKeyDefinition::class)]
final class FollowedConstraintTest extends TestCase
{
    public function testCarriesWhatIsNeededToFollowAKeyAndToRefuseIt(): void
    {
        $foreignKey = new ForeignKeyDefinition(['parent_id'], 'parents', ['id']);

        $constraint = new FollowedConstraint('children', 'fk', $foreignKey, 'DELETE');

        self::assertSame(
            ['children', 'fk', $foreignKey, 'DELETE'],
            [$constraint->childTable, $constraint->constraintName, $constraint->foreignKey, $constraint->sql],
        );
    }
}
