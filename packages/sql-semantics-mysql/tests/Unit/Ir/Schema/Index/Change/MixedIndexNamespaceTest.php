<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Change;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Change\AlterIndexes;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Change\MixedIndexNamespace;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\IndexRename;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(MixedIndexNamespace::class)]
#[Medium]
final class MixedIndexNamespaceTest extends TestCase
{
    public function testCheckAcceptsAReleasedName(): void
    {
        $semantics = new Semantics();
        $catalog = $semantics->context(['CREATE TABLE app.t (a INT, b INT, KEY first_key(a), KEY second_key(b))']);
        $value = $semantics->analyze('ALTER TABLE app.t DROP INDEX second_key, RENAME INDEX first_key TO second_key', $catalog);
        self::assertInstanceOf(AlterIndexes::class, $value);
        $dropped = $value->actions[0]->operation;
        $renamed = $value->actions[1]->operation;
        self::assertInstanceOf(IndexIdentity::class, $dropped);
        self::assertInstanceOf(IndexRename::class, $renamed);

        (new MixedIndexNamespace())->check([$dropped], [$renamed], $value->unmoved);

        self::assertSame($dropped->name->value, $renamed->destination->value);
    }
}
