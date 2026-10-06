<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;

#[CoversNothing]
#[Small]
final class TableElementTest extends TestCase
{
    public function testImplementationsDeclareTheInterface(): void
    {
        self::assertContains(TableElement::class, class_implements(ColumnDefinition::class));
    }

    public function testDeriveElementIsDeclaredByTheInterface(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $literal = new BooleanLiteral(true);
        $element = new CheckConstraint($literal);
        $element->deriveElement($derivation, $derivation->environment());

        self::assertTrue($derivation->facts()->covers($literal));
    }
}
