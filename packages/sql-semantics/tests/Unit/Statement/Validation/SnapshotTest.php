<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;
use SqlSemantics\Statement\Validation\Snapshot;

#[CoversClass(Snapshot::class)]
#[Small]
final class SnapshotTest extends TestCase
{
    public function testCloneCannotInventAnotherIdentity(): void
    {
        $name = new Name('name');
        $this->expectException(InvalidConstruction::class);
        $copy = clone $name;
        self::assertSame($name->value, $copy->value);
    }

    public function testSetCannotAddDynamicStateWithAssertionsCompiledOut(): void
    {
        $program = 'require ' . var_export(dirname(__DIR__, 4) . '/vendor/autoload.php', true) . ';' . <<<'PHP'
try {
    $name = new SqlSemantics\Statement\Identifier\Name('name');
    $name->extension = new stdClass();
} catch (SqlSemantics\Statement\Validation\Failure\InvalidConstruction $error) {
    echo 'rejected';
    exit(0);
}
exit(1);
PHP;
        $process = proc_open([PHP_BINARY, '-d', 'zend.assertions=-1', '-r', $program], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors === false ? 'Cannot read child errors.' : $errors);
        self::assertSame('rejected', $output);
    }

    public function testSerializeCannotCreateAnUncheckedRestorationFormat(): void
    {
        $this->expectException(InvalidConstruction::class);
        serialize(new Name('name'));
    }

    public function testUnserializeRejectsAHandWrittenPayloadBeforeInitializingState(): void
    {
        $class = Name::class;
        $payload = 'O:' . strlen($class) . ':"' . $class . '":0:{}';
        $this->expectException(InvalidConstruction::class);
        unserialize($payload);
    }
}
