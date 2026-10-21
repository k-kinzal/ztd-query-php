<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Resolution;

use MySqlMemory\Instance;
use MySqlMemory\Session\Resolution\Parameters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Variable\ParameterBinding;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(Parameters::class)]
#[Small]
final class ParametersTest extends TestCase
{
    public function testDerivationRetainsWrittenPositionsAndTheInitialPreparationType(): void
    {
        $first = new Parameter();
        $second = new Parameter();
        $row = new Row([$first, $second]);
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $session->preparation->domains[1] = \MySqlMemory\Typing\Domain::integer();
        $context = $session->semantics()->context([], true, session: $session->resolution());
        $derivation = (new Parameters())->derivation($row, $context, [1 => $session->preparation->domains[1]->resolved()]);
        $derivation->scalar($row, $derivation->environment());
        $facts = $derivation->facts();
        self::assertEquals(new ParameterBinding(0), $facts->scalar($first)->resolution);
        self::assertEquals(new ParameterBinding(1), $facts->scalar($second)->resolution);
        self::assertInstanceOf(Known::class, $facts->scalar($first)->type);
        self::assertInstanceOf(Known::class, $facts->scalar($second)->type);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::class, $facts->scalar($first)->type->descriptor);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::class, $facts->scalar($second)->type->descriptor);
        self::assertSame(Kind::String, $facts->scalar($first)->type->descriptor->kind);
        self::assertSame(16383, $facts->scalar($first)->type->descriptor->length);
        self::assertSame(Kind::Integer, $facts->scalar($second)->type->descriptor->kind);
        self::assertSame([0], \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::of($derivation->context)->unboundParameters);
        self::assertSame('unrelated invalid input', $session->text);
    }
}
