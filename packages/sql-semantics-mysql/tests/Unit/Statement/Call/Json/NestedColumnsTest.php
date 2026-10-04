<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Json\NestedColumns;
use SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(NestedColumns::class)]
#[Small]
final class NestedColumnsTest extends TestCase
{
    public function testDeriveColumnsFlattensTheNestedColumnsAsNullable(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $slots = (new NestedColumns(new StringLiteral(['$.b[*]']), [new OrdinalityColumn(new Name('n'))]))->deriveColumns($derivation, $derivation->environment());

        self::assertSame('n', $slots[0]->name?->value);
        self::assertSame(Nullability::Nullable, $slots[0]->nullability);
    }

    public function testRenderWritesTheNestedColumns(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new NestedColumns(new StringLiteral(['$.b[*]']), [new OrdinalityColumn(new Name('n'))]))->render($out);

        self::assertSame("NESTED PATH '$.b[*]' COLUMNS (n FOR ORDINALITY)", (new Lexical())->join($out->pieces()));
    }
}
