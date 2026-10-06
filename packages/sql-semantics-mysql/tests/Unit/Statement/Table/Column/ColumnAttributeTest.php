<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute;

#[CoversNothing]
#[Small]
final class ColumnAttributeTest extends TestCase
{
    public function testImplementationsDeclareTheInterface(): void
    {
        self::assertContains(ColumnAttribute::class, class_implements(KeywordAttribute::class));
    }

    public function testDeriveAttributeIsDeclaredByTheInterface(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $literal = new BooleanLiteral(true);
        $attribute = new DefaultLiteral($literal);
        $attribute->deriveAttribute($derivation, $derivation->environment());

        self::assertTrue($derivation->facts()->covers($literal));
    }
}
