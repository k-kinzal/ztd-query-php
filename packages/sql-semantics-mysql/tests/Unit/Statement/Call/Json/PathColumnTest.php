<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind;
use SqlSemantics\Platform\MySql\Statement\Call\Json\PathColumn;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(PathColumn::class)]
#[Small]
final class PathColumnTest extends TestCase
{
    public function testDeriveColumnsAnswersTheDeclaredType(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $type = new Integral(IntegralKind::Int);
        $default = new NumberLiteral('1');
        $slots = (new PathColumn(new Name('a'), $type, new StringLiteral(['$.a']), false, null, new JsonResponse(JsonResponseKind::Default, $default)))->deriveColumns($derivation, $derivation->environment());

        self::assertEquals([new OutputSlot(new Name('a'), new Known($type), Nullability::Nullable)], $slots);
        self::assertSame(Nullability::NotNull, $derivation->facts()->scalar($default)->nullability);
    }

    public function testRenderKeepsTheOrderOfTheResponses(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new PathColumn(new Name('a'), new Integral(IntegralKind::Int), new StringLiteral(['$.a']), true, new CollationName(new Name('utf8mb4_bin')), new JsonResponse(JsonResponseKind::Null), new JsonResponse(JsonResponseKind::Error), true))->render($out);

        self::assertSame("a INT COLLATE utf8mb4_bin EXISTS PATH '$.a' ERROR ON ERROR NULL ON EMPTY", (new Lexical())->join($out->pieces()));
    }
}
