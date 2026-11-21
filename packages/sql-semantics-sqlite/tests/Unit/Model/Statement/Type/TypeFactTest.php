<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Invalid;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\NullOnly;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\TypeFact;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(TypeFact::class)]
#[Medium]
final class TypeFactTest extends TestCase
{
    public function testTheClosedAlternativesTellKnownledgeFromMissingInputsAndFromWrongRequests(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE t (a INTEGER)');
        $declared = $semantics->inspect('SELECT a, b, NULL FROM t', [$table]);
        $open = $semantics->inspect('SELECT a FROM t');

        self::assertInstanceOf(Known::class, $declared->field(0)->type);
        self::assertInstanceOf(Invalid::class, $declared->field(1)->type);
        self::assertInstanceOf(NullOnly::class, $declared->field(2)->type);
        self::assertInstanceOf(Dependent::class, $open->field(0)->type);
    }
}
