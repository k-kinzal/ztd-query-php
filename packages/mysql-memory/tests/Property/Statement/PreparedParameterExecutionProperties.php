<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use MySqlMemory\Command\Prepared\ParameterBindings;
use MySqlMemory\Command\Prepared\PreparedCache;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use Tests\Eris\TestTrait;
use Tests\Generator\MySql\Statement\Literal\ParameterGenerator;

/**
 * Executes generated arithmetic through the parameter lifecycle used by both wire protocols.
 */
#[CoversNothing]
#[Large]
final class PreparedParameterExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Checks numeric results, column types and state restoration across repeated bindings.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesNumericMarkerContextsSurviveBindingAndRepeatedExecution(GrammarRelease $release): void
    {
        $this->forAll(ParameterGenerator::arithmetic($release))->then(static function (array $sample) use ($release): void {
            [$statement, $parameters, $expected, $field] = $sample;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $statement);
            self::assertIsArray($parameters);
            $values = [];
            foreach ($parameters as $parameter) {
                self::assertIsArray($parameter);
                self::assertIsInt($parameter[0]);
                self::assertInstanceOf(\MySqlMemory\Typing\Domain::class, $parameter[1]);
                $values[] = [$parameter[0], $parameter[1]];
            }
            $session = (new Instance(substr($release->value, 6)))->connect();
            $prepared = PreparedCache::capture($statement, $session);
            $bindings = ParameterBindings::capture($statement);
            $first = $prepared->execute($session, 'generated numeric preparation', $values, $bindings);
            $second = $prepared->execute($session, 'generated numeric preparation', $values, $bindings);
            self::assertInstanceOf(ResultSet::class, $first);
            self::assertInstanceOf(ResultSet::class, $second);
            if (is_float($expected)) {
                self::assertEqualsWithDelta($expected, (float) $first->rows[0][0], max(1e-14, abs($expected) * 1e-14));
            } else {
                self::assertSame([[$expected]], $first->rows);
            }
            self::assertSame($field, $first->columns[0]->type);
            self::assertSame($first->rows, $second->rows);
            self::assertSame($field, $second->columns[0]->type);
            self::assertSame([], $session->preparation->domains);
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * Keeps prepared domains while converting new argument and user-variable values.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesPreparationRetainsTypesAndCalculationPrecision(GrammarRelease $release): void
    {
        $this->forAll(ParameterGenerator::lifetime($release))->then(static function (array $sample) use ($release): void {
            [$statement, $parameters, $text, $precision, $expected] = $sample;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $statement);
            self::assertIsArray($parameters);
            self::assertIsString($text);
            self::assertIsInt($precision);
            self::assertIsArray($expected);
            $values = [];
            foreach ($parameters as $parameter) {
                self::assertIsArray($parameter);
                self::assertTrue(is_int($parameter[0]) || is_string($parameter[0]));
                self::assertInstanceOf(\MySqlMemory\Typing\Domain::class, $parameter[1]);
                $values[] = [$parameter[0], $parameter[1]];
            }
            $session = (new Instance(substr($release->value, 6)))->connect();
            $setting = $session->instance->catalog->find('div_precision_increment');
            self::assertNotNull($setting);
            $session->variables->set($setting, (string) $precision);
            $session->variables->assign('typed', $text, \MySqlMemory\Typing\Domain::string(strlen($text), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary()));
            $cache = PreparedCache::capture($statement, $session);
            $bindings = ParameterBindings::capture($statement);
            $before = $cache->definition;
            $result = $cache->execute($session, 'generated type lifetime', $values, $bindings);
            self::assertInstanceOf(ResultSet::class, $result);
            self::assertSame([$expected], $result->rows);
            self::assertSame($before, $cache->definition);
            self::assertSame([], $session->preparation->domains);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }
}
