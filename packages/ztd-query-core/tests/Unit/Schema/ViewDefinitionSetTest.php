<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\ViewDefinition;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(ViewDefinitionSet::class)]
#[UsesClass(ViewDefinition::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(SqlTokenKind::class)]
#[UsesClass(SqlTokenStream::class)]
final class ViewDefinitionSetTest extends TestCase
{
    public function testOrdersViewsAfterTheirDependencies(): void
    {
        $definitions = new ViewDefinitionSet();
        $definitions->register('summary', ViewDefinition::fromQuery('SELECT count(*) FROM active_users'));
        $definitions->register('active_users', ViewDefinition::fromQuery('SELECT * FROM users WHERE active = 1'));

        self::assertSame(
            ['active_users', 'summary'],
            array_keys($definitions->shadowQueries(['users'])),
        );
    }

    public function testOrdersThreeDependencyLevelsAndUnqualifiesEveryRelationKind(): void
    {
        $definitions = new ViewDefinitionSet();
        $definitions->register('summary', ViewDefinition::fromQuery('SELECT count(*) FROM tenant.active_users'));
        $definitions->register('active_users', ViewDefinition::fromQuery('SELECT * FROM tenant.eligible_users'));
        $definitions->register('eligible_users', ViewDefinition::fromQuery('SELECT * FROM tenant.users'));

        self::assertSame(
            [
                'eligible_users' => 'SELECT * FROM users',
                'active_users' => 'SELECT * FROM eligible_users',
                'summary' => 'SELECT count(*) FROM active_users',
            ],
            $definitions->shadowQueries(['users']),
        );
    }


    public function testKeepsCyclicDefinitionsDeterministic(): void
    {
        $definitions = new ViewDefinitionSet();
        $definitions->register('left_view', ViewDefinition::fromQuery('SELECT * FROM right_view'));
        $definitions->register('right_view', ViewDefinition::fromQuery('SELECT * FROM left_view'));

        self::assertSame(
            ['left_view', 'right_view'],
            array_keys($definitions->shadowQueries([])),
        );
    }
}
