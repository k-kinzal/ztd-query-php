<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Closure;
use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Extract;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Statement\Scalar;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ExtractStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Extract>}>
     */
    public static function providerStructures(): iterable
    {
        $sources = [
            static fn (): Scalar => new NumberLiteral('10'),
            static fn (): Scalar => new NumberLiteral('123456'),
            static fn (): Scalar => new NumberLiteral('1234567'),
            static fn (): Scalar => new NumberLiteral('20240101'),
            static fn (): Scalar => new NumberLiteral('20240101123456'),
            static fn (): Scalar => new NumberLiteral('10.25'),
            static fn (): Scalar => new StringLiteral(['10']),
            static fn (): Scalar => new StringLiteral(['123456']),
            static fn (): Scalar => new StringLiteral(['2024-01-02 03:04:05.123456']),
            static fn (): Scalar => new StringLiteral(['1 12:34:56.123456']),
            static fn (): Scalar => new TemporalLiteral(TemporalForm::Time, '12:34:56'),
            static fn (): Scalar => new TemporalLiteral(TemporalForm::Time, '-12:34:56'),
            static fn (): Scalar => new TemporalLiteral(TemporalForm::Date, '2024-01-02'),
        ];
        $statements = [];
        foreach (IntervalUnit::cases() as $unit) {
            array_push($statements, ...array_map(static fn (Closure $source): Extract => new Extract($unit, $source()), $sources));
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<Extract> $statements Every unit with numeric, textual and typed temporal operands
     */
    #[DataProvider('providerStructures')]
    public function testSemanticUnitsMatchNativeConversions(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $native->query("SET sql_mode='', timestamp=1700000000");
                $session = (new Instance($release))->connect('root', 'localhost');
                $session->run("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci; SET sql_mode='', timestamp=1700000000");
                $observed = array_map(static function (Extract $node) use ($native, $session): array {
                    $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $node);
                    return [serialize($expected), serialize($actual)];
                }, $statements);

                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
