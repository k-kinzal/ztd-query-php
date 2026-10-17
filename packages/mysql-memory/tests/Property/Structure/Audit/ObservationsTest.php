<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Audit;

use Fuzz\Structure\Audit\Observations;
use Fuzz\Target\EngineReports;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateLoadableFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\LoadableResult;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineStatus;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Verifies the field boundaries and negative controls of direct observation contracts.
 */
#[CoversNothing]
#[Small]
final class ObservationsTest extends TestCase
{
    /**
     * Keeps warning code types and raw inputs while comparing bounded legacy loader errors.
     */
    public function testCompareReusesTheLibraryContractWithoutChangingWarningTypes(): void
    {
        $first = "Can't open shared library 'absent' (errno: 2 /plugin/absent: cannot open shared object file: No such file or directory)";
        $second = str_replace('errno: 2 ', 'errno: 11 ', $first);
        $left = ['results' => [], 'error' => [1126, 'HY000', $first], 'warnings' => [['Error', '1126', $first]], 'disconnected' => false];
        $right = ['results' => [], 'error' => [1126, 'HY000', $second], 'warnings' => [['Error', '1126', $second]], 'disconnected' => false];
        $node = new CreateLoadableFunction(new Name('f'), LoadableResult::String, new Text('absent'));
        $compare = new Observations();

        self::assertSame(['matches' => true, 'contracts' => [\Fuzz\Target\LibraryErrors::CONTRACT]], $compare->compare($node, '5.7.44', $left, $right));
        self::assertSame($first, $left['error'][2]);
        self::assertFalse($compare->compare($node, '8.4.7', $left, $right)['matches']);
        $right['warnings'][0][1] = '1127';
        self::assertFalse($compare->compare($node, '5.7.44', $left, $right)['matches']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>, bool}>
     */
    public static function providerEngineObservations(): iterable
    {
        $columns = array_map(static fn (string $name): array => ['name' => $name, 'orgname' => '', 'type' => 253, 'flags' => 1, 'length' => 40], ['Type', 'Name', 'Status']);
        $native = ['results' => [['columns' => $columns, 'rows' => [['InnoDB', '', 'monitor']]]], 'warnings' => [], 'disconnected' => false];
        $memory = ['results' => [['columns' => $columns, 'rows' => []]], 'warnings' => [], 'disconnected' => false];
        yield 'empty memory physical report' => [$native, $memory, true];
        $changed = $memory;
        $changed['results'][0]['columns'][0]['length'] = 41;
        yield 'metadata stays exact' => [$native, $changed, false];
        $changed = $memory;
        $changed['results'][0]['rows'] = [['InnoDB', '', 'invented']];
        yield 'memory must be empty' => [$native, $changed, false];
        $changed = $native;
        $changed['results'][0]['rows'] = [['performance_schema', 'memory', '-1']];
        yield 'native quantity must be nonnegative' => [$changed, $memory, false];
        $changed = $memory;
        $changed['warnings'] = [['Warning', '1287', 'unexpected']];
        yield 'warnings stay exact' => [$native, $changed, false];
        yield 'errors stay exact' => [$native, $memory + ['error' => [1, 'HY000', 'unexpected']], false];
    }

    /**
     * @param array<string, mixed> $native Complete native observation
     * @param array<string, mixed> $memory Complete direct observation
     */
    #[DataProvider('providerEngineObservations')]
    public function testCompareOnlyExemptsValidatedEnginePayloads(array $native, array $memory, bool $equal): void
    {
        $compare = new Observations();
        $result = $compare->compare(new ShowEngineStatus(), '8.4.7', $native, $memory);

        self::assertSame($equal, $result['matches']);
        self::assertSame($equal, $result === ['matches' => true, 'contracts' => [EngineReports::CONTRACT]]);
        self::assertFalse($compare->compare(new ShowEngineStatus(new Name('other')), '8.4.7', $native, $memory)['matches']);
    }
}
