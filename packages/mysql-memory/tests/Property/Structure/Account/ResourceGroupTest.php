<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Account;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\AlterResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\DropResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupKind;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\SetResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Generated semantic names preserve group identity throughout a direct command history.
 */
#[CoversNothing]
#[Medium]
final class ResourceGroupTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 60; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Builds fresh operands for creation, alteration, assignment and removal without parsing SQL.
     */
    #[DataProvider('providerHistories')]
    public function testNamesRetainTheirIdentityAcrossCommands(string $release, int $seed): void
    {
        $choices = new Choices('resource-group-name:' . $seed);
        $character = $choices->one(['a', '猫', 'é', '😀', ' ', '`', "\t"]);
        $width = strlen(ResourceGroupName::decoded(new Name($character)));
        $text = str_repeat($character, $choices->integer(0, intdiv(64, $width)));
        $name = new ResourceGroupName(new Name($text));
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $execution = new Execution();
        $execution->runIn($session, new CreateResourceGroup($name, ResourceGroupKind::User));
        $execution->runIn($session, new AlterResourceGroup(new ResourceGroupName(new Name($name->value))));
        $execution->runIn($session, new SetResourceGroup(new ResourceGroupName(new Name($text))));

        self::assertNotNull($session->instance->registry->resourceGroups->find($name->value));
        self::assertTrue($session->instance->registry->resourceGroups->busy($name->value));

        $execution->runIn($session, new DropResourceGroup(new ResourceGroupName(new Name($name->value)), true));

        self::assertNull($session->instance->registry->resourceGroups->find($name->value));
    }
    /**
     * Creation and alteration consume the same signed value without reading SQL.
     */
    #[DataProvider('providerHistories')]
    public function testPrioritiesRetainTheirValuesAcrossCommands(string $release, int $seed): void
    {
        $choices = new Choices('resource-group-priority:' . $seed);
        $kind = $choices->one(ResourceGroupKind::cases());
        $created = $kind === ResourceGroupKind::System ? $choices->integer(-20, 0) : $choices->integer(0, 19);
        $changed = $kind === ResourceGroupKind::System ? $choices->integer(-20, 0) : $choices->integer(0, 19);
        $zeros = str_repeat('0', $choices->integer(0, 8));
        $negativeZero = $choices->one([true, false]);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $execution = new Execution();
        $execution->runIn($session, new CreateResourceGroup(new ResourceGroupName(new Name('g')), $kind, priority: new ThreadPriority($created < 0 || $created === 0 && $negativeZero, new Numeral($zeros . abs($created)))));
        $group = $session->instance->registry->resourceGroups->find('g');

        self::assertNotNull($group);
        self::assertSame($created, $group->priority);
        self::assertSame($kind === ResourceGroupKind::System, $group->system);

        $execution->runIn($session, new AlterResourceGroup(new ResourceGroupName(new Name('g')), priority: new ThreadPriority($changed < 0 || $changed === 0 && $negativeZero, new Numeral($zeros . abs($changed)))));

        self::assertSame($changed, $group->priority);
    }

    /**
     * The same priority domain is executable when the platform ignores priorities.
     */
    #[DataProvider('providerHistories')]
    public function testPlatformWithoutPrioritiesAcceptsEveryGeneratedInteger(string $release, int $seed): void
    {
        $choices = new Choices('resource-group-platform:' . $seed);
        $kind = $choices->one(ResourceGroupKind::cases());
        $number = $choices->one([0, 1, 19, 20, 21, 2147483647, $choices->integer(0, 2147483647)]);
        $negative = $choices->one([true, false]);
        $session = (new Instance($release, resourcePriorities: false))->connect();
        $session->text = 'unrelated invalid SQL';
        $execution = new Execution();
        $execution->runIn($session, new CreateResourceGroup(new ResourceGroupName(new Name('g')), $kind, priority: new ThreadPriority($negative, new Numeral((string) $number))));
        $group = $session->instance->registry->resourceGroups->find('g');

        self::assertNotNull($group);
        self::assertSame(0, $group->priority);
        self::assertSame($number === 0 ? 0 : 1, $session->diagnostics->count());

        $execution->runIn($session, new AlterResourceGroup(new ResourceGroupName(new Name('g')), priority: new ThreadPriority($negative, new Numeral('000' . $number))));

        self::assertSame(0, $group->priority);
        self::assertSame($number === 0 ? 0 : 1, $session->diagnostics->count());
    }

}
