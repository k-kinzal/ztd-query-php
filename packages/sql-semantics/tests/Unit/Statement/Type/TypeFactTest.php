<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;
use SqlSemantics\Statement\Type\TypeFact;

#[CoversClass(TypeFact::class)]
#[Medium]
final class TypeFactTest extends TestCase
{
    public function testTheClosedAlternativesTellKnownledgeFromMissingInputsAndFromWrongRequests(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $declared = $semantics->analyze('SELECT a, b, NULL FROM t', [$table]);
        $open = $semantics->analyze('SELECT a FROM t');

        self::assertInstanceOf(Known::class, $declared->field(0)->type);
        self::assertInstanceOf(Invalid::class, $declared->field(1)->type);
        self::assertInstanceOf(NullOnly::class, $declared->field(2)->type);
        self::assertInstanceOf(Dependent::class, $open->field(0)->type);
    }
}
