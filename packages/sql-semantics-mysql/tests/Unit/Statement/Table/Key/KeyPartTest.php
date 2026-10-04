<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart;

#[CoversNothing]
#[Small]
final class KeyPartTest extends TestCase
{
    public function testImplementationsDeclareTheInterface(): void
    {
        self::assertContains(KeyPart::class, class_implements(ColumnPart::class));
    }

    public function testDeriveKeyPartIsDeclaredByTheInterface(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $literal = new BooleanLiteral(true);
        $part = new ExpressionPart($literal);
        $part->deriveKeyPart($derivation, $derivation->environment());

        self::assertTrue($derivation->facts()->covers($literal));
    }
}
