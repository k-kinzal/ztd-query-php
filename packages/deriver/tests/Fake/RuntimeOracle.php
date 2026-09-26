<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Value\Term;
use JsonException;
use RuntimeException;

/**
 * Executes only generated test fixtures in a separate PHP 8.3 process.
 * @visibility root
 */
final class RuntimeOracle
{
    /**
     * Runs a trusted fixture under the target runtime and captures its return.
     * @param string $source Generated PHP fixture
     * @return Term Independently observed value
     * @throws JsonException If data cannot be represented in JSON
     * @throws RuntimeException If the runtime cannot execute or return fixture data
     */
    public static function evaluate(string $source): Term
    {
        $observation = self::observe($source);
        if ($observation['exception'] !== '' || $observation['diagnostics'] !== []) {
            throw new RuntimeException('Expected a warning-free normal runtime fixture: ' . $observation['exception'] . ' ' . implode('; ', $observation['diagnostics']));
        }
        return $observation['value'];
    }

    /**
     * Captures normal values, throwable classes and diagnostics from trusted runtime fixtures.
     * @param string $source Generated PHP fixture
     * @return array{value: Term, exception: string, diagnostics: list<string>} Target observations
     * @throws JsonException If runtime data cannot be decoded
     * @throws RuntimeException If the fixture cannot be executed or has an invalid envelope
     */
    public static function observe(string $source): array
    {
        $suffix = <<<'PHP'

$deriverOracleDiagnostics = [];
set_error_handler(static function(int $level, string $message) use (&$deriverOracleDiagnostics): bool {
    $deriverOracleDiagnostics[] = $level . ':' . $message;
    return true;
});
try {
    $deriverOracleValue = target();
    $deriverOracleException = '';
} catch (\Throwable $deriverOracleFailure) {
    $deriverOracleValue = null;
    $deriverOracleException = get_class($deriverOracleFailure);
}
echo json_encode(['value' => $deriverOracleValue, 'exception' => $deriverOracleException, 'diagnostics' => $deriverOracleDiagnostics], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
PHP;
        $observation = json_decode(self::execute(self::harness($source, $suffix)), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($observation) || !array_key_exists('value', $observation) || !is_string($observation['exception'] ?? null) || !is_array($observation['diagnostics'] ?? null)) {
            throw new RuntimeException('The target returned an invalid observation envelope.');
        }
        $diagnostics = [];
        foreach ($observation['diagnostics'] as $diagnostic) {
            if (!is_string($diagnostic)) {
                throw new RuntimeException('The target returned a malformed diagnostic.');
            }
            $diagnostics[] = $diagnostic;
        }
        return ['value' => Term::fromNative($observation['value']), 'exception' => $observation['exception'], 'diagnostics' => $diagnostics];
    }

    /**
     * Runs only generated test programs under the explicitly selected PHP 8.3 executable.
     * @param string $program Trusted test program including its observation harness
     * @return string Encoded runtime observation
     * @throws RuntimeException If the target process cannot complete the fixture
     */
    public static function execute(string $program): string
    {
        $configured = getenv('DERIVER_PHP83_BINARY');
        $binary = is_string($configured) && $configured !== '' ? $configured : (PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 3 ? PHP_BINARY : '/opt/homebrew/opt/php@8.3/bin/php');
        if (!is_executable($binary)) {
            throw new RuntimeException('Set DERIVER_PHP83_BINARY to a PHP 8.3 executable for differential tests.');
        }
        $pipes = [];
        $process = proc_open([$binary, '-n', '-d', 'memory_limit=32M', '-d', 'max_execution_time=2', '-d', 'display_errors=stderr'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, []);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start the isolated PHP differential oracle.');
        }
        fwrite($pipes[0], $program);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0 || $error !== '') {
            throw new RuntimeException('Oracle rejected the generated fixture: ' . $error);
        }
        if ($output === false) {
            throw new RuntimeException('Oracle produced no output.');
        }
        return $output;
    }
    /**
     * Places observations in the global namespace for fixtures with bracketed namespaces.
     * @param string $source Trusted fixture source
     * @param string $suffix Observation statements
     * @return string Executable fixture and observation harness
     */
    public static function harness(string $source, string $suffix): string
    {
        $namespace = false;
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                $namespace = $namespace || $token[0] === T_NAMESPACE;
            } elseif ($namespace && $token === '{') {
                return $source . "\nnamespace {" . $suffix . "\n}";
            } elseif ($namespace && $token === ';') {
                break;
            }
        }
        return $source . $suffix;
    }
}
