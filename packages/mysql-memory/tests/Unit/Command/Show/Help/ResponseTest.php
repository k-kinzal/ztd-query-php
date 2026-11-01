<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show\Help;

use MySqlMemory\Command\Show\Help\Response;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Session\SqlModes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Response::class)]
#[Small]
final class ResponseTest extends TestCase
{
    public function testSentUsesResultEncodingAndHelpMetadata(): void
    {
        $session = (new Instance(globals: ['character_set_results' => 'latin1']))->connect();
        $context = new Context(new SqlModes([]), $session->diagnostics, $session->variables, 0.0);
        $result = (new Response(['name' => 64, 'description' => 1000, 'example' => 1000], [['Café', 'Description', 'Example']]))->sent($context);

        self::assertSame([["Caf\xE9", 'Description', 'Example']], $result->rows);
        self::assertSame([64, 1000, 1000], array_column($result->columns, 'length'));
        self::assertSame([31, 31, 31], array_column($result->columns, 'decimals'));
    }
}
