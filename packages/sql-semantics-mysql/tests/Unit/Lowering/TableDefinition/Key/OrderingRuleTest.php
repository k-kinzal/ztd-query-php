<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\OrderingRule;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\ExplicitIndexOrder;

#[CoversClass(OrderingRule::class)]
#[Small]
final class OrderingRuleTest extends TestCase
{
    public function testPartsRefusesEvenAnUnreachableModernIndexBeforeExpressionPreparation(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() IF FALSE THEN CREATE FULLTEXT INDEX i ON t (a DESC, ((SELECT x FROM q))); END IF');
            self::fail('A stored definition cannot defer incompatible modern index ordering.');
        } catch (RejectedInput $failure) {
            $problem = $failure->problem;
            self::assertInstanceOf(ExplicitIndexOrder::class, $problem);
            self::assertSame('p', $problem->table->name->value);
            self::assertFalse($failure->aborts);
        }
    }
}
