<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Definition\TriggerInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\TriggerSchemaMismatch;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(TriggerInput::class)]
#[Medium]
final class TriggerInputTest extends TestCase
{
    public function testRefusedPreservesCatalogConditionsAcrossTheSameTypedHeader(): void
    {
        $session = (new Instance('8.4.7', [], ['a', 'b']))->connect();
        $problem = new TriggerSchemaMismatch(new QualifiedName(new Name('tr'), new Name('a')), new TriggerTable(new QualifiedName(new Name('t'), new Name('b'))));
        $failure = new RejectedInput($problem, 0, false);
        $input = new TriggerInput();
        $sql = 'CREATE TRIGGER a.tr BEFORE INSERT ON b.t FOR EACH ROW SET @x=1';
        self::assertSame(1146, $input->refused($failure, $sql, $session)->getCode());
        $session->query('CREATE TABLE b.t(a INT); INSERT INTO b.t VALUES(7)');
        self::assertSame(1435, $input->refused($failure, $sql, $session)->getCode());
        $session->preparation->inputPrepared = true;
        self::assertSame(1295, $input->refused($failure, $sql, $session)->getCode());
        self::assertSame($problem, $failure->problem);
    }
}
