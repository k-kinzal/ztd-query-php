<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Definition\DeferredPreparation;
use MySqlMemory\Session\Parse\Definition\TableInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(DeferredPreparation::class)]
#[Medium]
final class DeferredPreparationTest extends TestCase
{
    public function testRetainsAnIncompletePreparedDefinitionWithoutReportingASqlError(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->preparation->inputPrepared = true;
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $header = new M\Table\Problem\Creation\RefusedCreateTail(new QualifiedName(new Name('t')), [], [], 0, false, new M\Partition\Problem\RefusedScalar($row));
        $this->expectException(DeferredPreparation::class);
        $this->expectExceptionMessage('The prepared instruction retains a condition resolved during execution.');

        (new TableInput())->resolve($header, new RejectedInput($header, 0), '', $session);
    }
}
