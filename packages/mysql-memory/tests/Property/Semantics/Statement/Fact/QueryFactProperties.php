<?php

declare(strict_types=1);

namespace Tests\Property\Semantics\Statement\Fact;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Fact\QueryFact as Model;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class QueryFactProperties extends TestCase
{
    /**
     * @return iterable<string, array{non-negative-int}>
     */
    public static function providerSamples(): iterable
    {
        for ($sample = 0; $sample < (new \Tests\Generator\Samples())->count(); $sample++) {
            yield 'sample ' . $sample => [$sample];
        }
    }
    /**
     * Runs the generated analysis result through the ordinary dispatch and execution boundary.
     * @param non-negative-int $sample
     * @throws JsonException When the semantic type inventory cannot be read
     */
    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $seed = getenv('MYSQL_MEMORY_PBT_SEED');
        $generator = new \Tests\Generator\Semantics\Statement\Fact\QueryFactGenerator();
        $value = $generator->sample(new \Tests\Generator\Choices(($seed === false || $seed === '' ? 'QueryFact' : $seed) . ':' . $sample));
        $operation = $generator->operation;
        self::assertNotNull($operation);
        self::assertSame($value, $operation->facts->output);
        $profile = $operation->profile();
        $database = $operation->context->searchPath[0]->value;
        $mode = (new \Tests\Generator\Profiles())->mode($profile);
        $session = (new \MySqlMemory\Instance(substr($profile->grammar->value, 6), ['sql_mode' => $mode], [$database]))->connect(database: $database);
        $parameters = array_fill(0, (new \Tests\Generator\Bindings())->count($operation->statement), [null, \MySqlMemory\Typing\Domain::null()]);
        try {
            $reply = (new \MySqlMemory\Session\Execution($session))->perform($operation, (new \MySqlMemory\Command\Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (\MySqlMemory\Error\SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [\MySqlMemory\Error\Family\StatementError::UnknownError, \MySqlMemory\Error\Family\StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }

    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
}
