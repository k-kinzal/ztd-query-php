<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Write;

use MySqlMemory\Program\Write\UpdateUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateUse::class)]
#[Small]
final class UpdateUseTest extends TestCase
{
    public function testErrorOpensTheOriginalTableBeforeTheColumnContradiction(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = $session->analyze("CREATE PROCEDURE p() UPDATE t,JSON_TABLE('[]', '$[*]' COLUMNS (b FOR ORDINALITY,b FOR ORDINALITY)) j SET t.a=2");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        $write = $operation->statement->body;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredUpdate::class, $write);
        $session->text = 'unrelated invalid input';
        self::assertSame(1146, (new UpdateUse())->error($write, $session)->getCode());
        $session->query('CREATE TABLE t(a INT)');
        $session->text = 'unrelated invalid input';
        self::assertSame(1060, (new UpdateUse())->error($write, $session)->getCode());
    }

    public function testRaiseKeepsAnUnreachedInstructionDormant(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query("CREATE PROCEDURE p() BEGIN IF @run THEN UPDATE JSON_TABLE('[]', '$[*]' COLUMNS (b FOR ORDINALITY,b FOR ORDINALITY)) j SET b=2; END IF; END");
        self::assertNotEmpty($session->query('SET @run=0; CALL p()'));
        $session->query('SET @run=1');
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1060);
        $session->query('CALL p()');
    }
}
