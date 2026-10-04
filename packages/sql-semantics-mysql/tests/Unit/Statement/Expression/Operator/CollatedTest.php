<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\BinaryCast;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(Collated::class)]
#[Medium]
final class CollatedTest extends TestCase
{
    public function testDeriveScalarHasTheTypeOfTheOperand(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));

        self::assertEquals(new Known(new Character(CharacterKind::VarChar)), $derivation->scalar(new Collated(new StringLiteral(['a']), new Name('utf8mb4_bin')), $derivation->environment())->type);
    }

    public function testRenderChainsCollationsFromTheLeft(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new Collated(new Collated(new StringLiteral(['a']), new Name('utf8mb4_bin')), new Name('utf8mb4_0900_ai_ci')))->render($out);

        self::assertSame("'a' COLLATE utf8mb4_bin COLLATE utf8mb4_0900_ai_ci", (new Lexical())->join($out->pieces()));
    }

    public function testABinaryCastOperandIsRejected(): void
    {
        $this->expectExceptionMessage('The operand of COLLATE needs a grouping to keep its place.');

        new Collated(new BinaryCast(new StringLiteral(['a'])), new Name('utf8mb4_bin'));
    }
}
