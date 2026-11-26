<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Change;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Change\AlterIndexes;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Change\MixedIndexChanges;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexAutoIncrement;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexRemovalTarget;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(MixedIndexChanges::class)]
#[Medium]
final class MixedIndexChangesTest extends TestCase
{
    public function testCheckKeepsAGeneratingKeyThatMoves(): void
    {
        $semantics = new Semantics();
        $catalog = $semantics->context(['CREATE TABLE app.t (a INT AUTO_INCREMENT, b INT, PRIMARY KEY(a), KEY by_a(a,b))']);
        $value = $semantics->analyze('ALTER TABLE app.t RENAME INDEX by_a TO renamed, DROP PRIMARY KEY', $catalog);
        self::assertInstanceOf(AlterIndexes::class, $value);
        $target = $value->actions[1]->operation;
        self::assertInstanceOf(IndexIdentity::class, $target);

        $resolved = new IndexIdentity($target->owner, new Identifier('PRIMARY'), $target->role);
        $proof = new IndexRemovalTarget($resolved, new IndexAutoIncrement(new Identifier('a'), [new Identifier('by_a')]));
        (new MixedIndexChanges())->check($value->owner, $value->actions, [$proof], [], $value->policy, []);

        self::assertSame('PRIMARY', $target->name->value);
    }
}
