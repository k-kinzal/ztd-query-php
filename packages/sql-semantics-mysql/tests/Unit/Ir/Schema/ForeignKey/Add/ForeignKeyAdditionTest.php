<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAddition;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ForeignKeyAddition::class)]
#[Small]
final class ForeignKeyAdditionTest extends TestCase
{
    public function testToStringRetainsOrderedFieldsAndExplicitEffects(): void
    {
        $addition = NativeAdditionFixture::addition('child_ibfk_1', true, 'new_index', new Identifier('new_index'));

        self::assertSame('ADD FOREIGN KEY `new_index` (`owner_id`) REFERENCES `app`.`parent` (`id`) ON UPDATE NO ACTION ON DELETE CASCADE', $addition->toString());
    }
}
