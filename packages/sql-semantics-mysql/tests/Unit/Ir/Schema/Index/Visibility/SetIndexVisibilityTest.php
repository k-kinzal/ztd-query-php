<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Visibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexLock;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\IndexVisibilityEdit;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\SecondaryIndexKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\SetIndexVisibility;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(SetIndexVisibility::class)]
#[Small]
final class SetIndexVisibilityTest extends TestCase
{
    public function testToStringPreservesRepeatedActionsAndTheEffectivePolicy(): void
    {
        $name = new TableName(new Identifier('app'), new Identifier('t'));
        $owner = new TableIdentity($name, ExternalRelationKind::TemporaryTable);
        $index = new Identifier('first_key');
        $kind = SecondaryIndexKind::Ordinary;
        $first = new IndexVisibilityEdit($index, $kind, false);
        $last = new IndexVisibilityEdit($index, $kind, true);
        $policy = new IndexChangePolicy(IndexAlgorithm::Copy, IndexLock::None);
        $value = new SetIndexVisibility($owner, [&$first, $last], policy: $policy);
        $first = $last;

        self::assertSame(
            'ALTER TABLE `app`.`t` ALTER INDEX `first_key` INVISIBLE, ALTER INDEX `first_key` VISIBLE, ALGORITHM=COPY, LOCK=NONE',
            $value->toString(),
        );
    }

    public function testProfileIdShowsTheSelectedNativeRelease(): void
    {
        $name = new TableName(new Identifier('app'), new Identifier('t'));
        $owner = new TableIdentity($name, ExternalRelationKind::BaseTable);
        $edit = new IndexVisibilityEdit(new Identifier('probe'), SecondaryIndexKind::Ordinary, true);

        self::assertStringStartsWith('mysql-8.4.7/', (new SetIndexVisibility($owner, [$edit], GrammarRelease::MySql847))->profileId());
    }
}
