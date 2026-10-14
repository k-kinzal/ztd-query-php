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
}
