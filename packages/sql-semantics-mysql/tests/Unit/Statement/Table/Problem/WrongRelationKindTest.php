<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\KindRefusal;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind;

#[CoversClass(WrongRelationKind::class)]
#[Small]
final class WrongRelationKindTest extends TestCase
{
    public function testMessageNamesTheRelationAndTheRefusal(): void
    {
        self::assertSame('shop.t is not VIEW.', (new WrongRelationKind(new QualifiedName(new Name('t'), new Name('shop')), KindRefusal::NotView))->message());
        self::assertSame('v is not BASE TABLE.', (new WrongRelationKind(new QualifiedName(new Name('v')), KindRefusal::NotBaseTable))->message());
    }
}
