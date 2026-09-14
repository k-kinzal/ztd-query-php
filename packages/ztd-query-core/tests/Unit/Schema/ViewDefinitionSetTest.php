<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\ViewDefinition;
use ZtdQuery\Schema\ViewDefinitionSet;

#[CoversClass(ViewDefinitionSet::class)]
#[UsesClass(ViewDefinition::class)]
final class ViewDefinitionSetTest extends TestCase
{
    public function testOrdersViewsAfterTheirDependencies(): void
    {
        $definitions = new ViewDefinitionSet();
        $summary = new ViewDefinition('summary query', ['active_users']);
        $activeUsers = new ViewDefinition('active users query', ['users']);
        $definitions->register('summary', $summary);
        $definitions->register('active_users', $activeUsers);

        self::assertSame(
            ['active_users' => $activeUsers, 'summary' => $summary],
            $definitions->orderedDefinitions(),
        );
    }

    public function testOrdersThreeDependencyLevels(): void
    {
        $definitions = new ViewDefinitionSet();
        $summary = new ViewDefinition('summary query', ['active_users']);
        $activeUsers = new ViewDefinition('active users query', ['eligible_users']);
        $eligibleUsers = new ViewDefinition('eligible users query', ['users']);
        $definitions->register('summary', $summary);
        $definitions->register('active_users', $activeUsers);
        $definitions->register('eligible_users', $eligibleUsers);

        self::assertSame(
            [
                'eligible_users' => $eligibleUsers,
                'active_users' => $activeUsers,
                'summary' => $summary,
            ],
            $definitions->orderedDefinitions(),
        );
    }

    public function testKeepsCyclicDefinitionsDeterministic(): void
    {
        $definitions = new ViewDefinitionSet();
        $definitions->register('left_view', new ViewDefinition('left query', ['right_view']));
        $definitions->register('right_view', new ViewDefinition('right query', ['left_view']));

        self::assertSame(
            ['left_view', 'right_view'],
            array_keys($definitions->orderedDefinitions()),
        );
    }

    public function testRegisterMakesAViewOneOfTheSet(): void
    {
        $definitions = new ViewDefinitionSet();
        $view = new ViewDefinition('query', []);

        $definitions->register('summary', $view);

        self::assertSame(['summary' => $view], $definitions->orderedDefinitions());
    }

    public function testRegisterUnderANameAlreadyThereTakesThePlaceOfIt(): void
    {
        $definitions = new ViewDefinitionSet();
        $replacement = new ViewDefinition('second query', []);
        $definitions->register('summary', new ViewDefinition('first query', []));
        $definitions->register('summary', $replacement);

        self::assertSame(['summary' => $replacement], $definitions->orderedDefinitions());
    }

    public function testHasAnswersForAViewTheSetKnows(): void
    {
        $definitions = new ViewDefinitionSet();
        $definitions->register('summary', new ViewDefinition('query', []));

        self::assertTrue($definitions->has('summary'));
        self::assertFalse($definitions->has('other'));
    }

    public function testHasAnyViewsIsFalseUntilOneIsRegistered(): void
    {
        $definitions = new ViewDefinitionSet();

        self::assertFalse($definitions->hasAnyViews());

        $definitions->register('summary', new ViewDefinition('query', []));

        self::assertTrue($definitions->hasAnyViews());
    }

    public function testOrderedDefinitionsIsEmptyForASetWithNoViews(): void
    {
        self::assertSame([], (new ViewDefinitionSet())->orderedDefinitions());
    }
}
