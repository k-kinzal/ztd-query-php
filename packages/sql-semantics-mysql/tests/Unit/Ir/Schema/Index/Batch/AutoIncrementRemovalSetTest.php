<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Batch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Batch\AutoIncrementRemovalSet;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexes;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(AutoIncrementRemovalSet::class)]
#[Medium]
final class AutoIncrementRemovalSetTest extends TestCase
{
    public function testCheckAcceptsASurvivorOutsideTheEntireSet(): void
    {
        $semantics = new Semantics();
        $catalog = $semantics->context([
            'CREATE TABLE app.t (a INT AUTO_INCREMENT, b INT, c INT, PRIMARY KEY (a), KEY removed (a,b), KEY retained (a,c))',
        ]);
        $value = $semantics->analyze('ALTER TABLE app.t DROP PRIMARY KEY, DROP INDEX removed', $catalog);

        self::assertInstanceOf(DropIndexes::class, $value);
        self::assertSame('PRIMARY', $value->targets[0]->name->value);
        self::assertSame('removed', $value->targets[1]->name->value);
    }
}
