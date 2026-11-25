<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemoval;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalKind;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalSet;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(ForeignKeyRemovalSet::class)]
#[Small]
final class ForeignKeyRemovalSetTest extends TestCase
{
    public function testCheckKeepsRepeatedLegacyTemporaryActions(): void
    {
        $owner = new TableIdentity(new TableName(new Identifier('app'), new Identifier('c')), ExternalRelationKind::TemporaryTable);
        $action = new ForeignKeyRemoval(null, ForeignKeyRemovalKind::IgnoredOnTemporary);
        (new ForeignKeyRemovalSet())->check($owner, [$action, $action], [], GrammarRelease::MySql5744, new IndexChangePolicy());

        self::assertSame('DROP FOREIGN KEY ``', $action->toString());
    }

    public function testLegacyDistinguishesTemporaryAndDuplicateSemantics(): void
    {
        $rule = new ForeignKeyRemovalSet();

        self::assertTrue($rule->legacy(GrammarRelease::MySql5744));
        self::assertFalse($rule->legacy(GrammarRelease::MySql8044));
    }
}
