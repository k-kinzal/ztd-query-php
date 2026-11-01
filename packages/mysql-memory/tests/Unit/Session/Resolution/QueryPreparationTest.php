<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Resolution;

use MySqlMemory\Instance;
use MySqlMemory\Session\Resolution\QueryPreparation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(QueryPreparation::class)]
#[Small]
final class QueryPreparationTest extends TestCase
{
    public function testProfileRetainsTheBoundQueryInterpretation(): void
    {
        $context = (new Instance())->connect()->semantics()->context();
        $derivation = new Derivation($context);
        $query = new M\Query\ValuesQuery([new M\Query\ValueRow([])]);
        $derivation->query($query, $derivation->environment());
        $input = new QueryPreparation($query, (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation), $context->profile);
        self::assertSame($context->profile, $input->profile());
        self::assertSame($query, $input->statement);
        self::assertNotEmpty($input->facts->diagnostics);
    }
}
