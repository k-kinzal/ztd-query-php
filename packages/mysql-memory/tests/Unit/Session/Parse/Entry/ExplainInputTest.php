<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Session\Parse\Entry\ExplainInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ExplainInput::class)]
#[Small]
final class ExplainInputTest extends TestCase
{
    public function testErrorPreservesEachOptionRefusal(): void
    {
        $input = new ExplainInput();

        self::assertSame(1235, $input->error(new RefusedConnectionExplain(true))->getCode());
        self::assertSame(6009, $input->error(new RefusedConnectionExplain(false))->getCode());
        self::assertSame('EXPLAIN ANALYZE does not support the INTO clause.', $input->error(new RefusedExplainInto(analyze: true))->getMessage());
        self::assertSame(6006, $input->error(new RefusedExplainInto())->getCode());
        self::assertSame(6007, $input->error(new RefusedExplainInto(new Name('traditional')))->getCode());
    }

    public function testErrorUsesTheNativeMessageByteLimitForTheRetainedTarget(): void
    {
        $problem = new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\InvalidExplainTarget(new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new Name(str_repeat('a', 101))));
        $error = (new ExplainInput())->error($problem);
        self::assertSame(3061, $error->getCode());
        self::assertSame("User variable name '" . str_repeat('a', 100) . "' is illegal", $error->getMessage());
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerReadingRefusals(): iterable
    {
        yield 'unknown format' => ['8.4.7', 'EXPLAIN FORMAT=XML FOR CONNECTION 0', 1791];
        yield 'legacy tree' => ['5.7.44', 'EXPLAIN FORMAT=TREE FOR CONNECTION 0', 1791];
        yield 'analyze for connection' => ['8.0.44', 'EXPLAIN ANALYZE FORMAT=TRADITIONAL FOR CONNECTION 0', 1235];
        yield 'implicit format' => ['8.4.7', 'EXPLAIN INTO @v FOR CONNECTION 0', 6006];
        yield 'wrong format' => ['8.4.7', 'EXPLAIN FORMAT=TREE INTO @v FOR CONNECTION 0', 6007];
        yield 'connection into' => ['8.4.7', 'EXPLAIN FORMAT=JSON INTO @v FOR CONNECTION 0', 6009];
        yield 'invalid target' => ['8.4.7', "EXPLAIN FORMAT=JSON INTO @'' FOR CONNECTION 0", 3061];
        yield 'unknown format before the target' => ['8.4.7', "EXPLAIN FORMAT=XML INTO @'' FOR CONNECTION 0", 1791];
        yield 'implicit format before a later syntax error' => ['8.4.7', 'EXPLAIN INTO @v SELECT * FROM', 6006];
        yield 'legacy analyze into' => ['8.1.0', 'EXPLAIN ANALYZE FORMAT=JSON INTO @v SELECT 1', 6005];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerReadingRefusals')]
    public function testErrorIsReportedForEveryOptionRefusedWhileReading(string $version, string $sql, int $code): void
    {
        $reply = (new \MySqlMemory\Instance($version))->connect()->run($sql)[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply);
        self::assertSame($code, $reply->getCode());
    }
}
