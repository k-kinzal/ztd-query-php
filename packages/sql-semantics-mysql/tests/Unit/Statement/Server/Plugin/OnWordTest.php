<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\OnWord;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(OnWord::class)]
#[Medium]
final class OnWordTest extends TestCase
{
    public function testRenderWritesOn(): void
    {
        self::assertSame("INSTALL COMPONENT 'c' SET c.v = ON", (new Inspection())->inspect("install component 'c' set c.v = on")->toString());
    }

    public function testDeriveScalarIsAString(): void
    {
        $install = (new Inspection())->inspect("INSTALL COMPONENT 'c' SET c.v = ON");
        self::assertInstanceOf(InstallComponent::class, $install->statement);

        self::assertEquals(new Known(new Character(CharacterKind::VarChar)), $install->facts->scalar($install->statement->settings[0]->value)->type);
    }
}
