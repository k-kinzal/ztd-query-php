<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn;
use SqlSemantics\Platform\MySql\Statement\Call\Json\PathColumn;
use SqlSemantics\Platform\MySql\Statement\Call\JsonTableColumn;
use SqlSemantics\Statement\Identifier\Name;

#[CoversNothing]
#[Small]
final class JsonTableColumnTest extends TestCase
{
    public function testDeriveColumnsAnswersTheOutputSlots(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $slots = (new OrdinalityColumn(new Name('n')))->deriveColumns($derivation, $derivation->environment());

        self::assertSame('n', $slots[0]->name?->value);
        self::assertContains(JsonTableColumn::class, class_implements(PathColumn::class));
    }
}
