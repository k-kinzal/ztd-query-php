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
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(TypeFact::class)]
#[Medium]
final class TypeFactTest extends TestCase
{
    public function testTheClosedAlternativesTellKnownledgeFromMissingInputsAndFromWrongRequests(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INTEGER)');
        $declared = $semantics->diagnose('SELECT a, b, NULL FROM t', [$table]);
        $open = $semantics->diagnose('SELECT a FROM t');

        self::assertInstanceOf(Known::class, $declared->field(0)->type);
        self::assertInstanceOf(Invalid::class, $declared->field(1)->type);
        self::assertInstanceOf(NullOnly::class, $declared->field(2)->type);
        self::assertInstanceOf(Dependent::class, $open->field(0)->type);
    }
}
