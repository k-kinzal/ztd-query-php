<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Structure\Audit\Comparison;
use Fuzz\Structure\Audit\Explain\ScanMeasurements;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversNothing]
#[Large]
final class ExplainMeasurementsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerReleases')]
    public function testIndependentNativeInstancesAndMemoryRetainExactRowsAndDiagnostics(string $release): void
    {
        $node = new M\Utility\Explain\Explain(new M\QueryRoot(new M\Query\ExplicitTable(new QualifiedName(new Name('t')))), analyze: true);
        $profile = new LanguageProfile(GrammarRelease::from('mysql-' . $release));
        $first = (new Comparison())->compare($node, $profile, port: 29750);
        $second = (new Comparison())->compare($node, $profile, port: 29750);
        self::assertTrue($first['matches'], json_encode($first, JSON_THROW_ON_ERROR));
        self::assertTrue($second['matches'], json_encode($second, JSON_THROW_ON_ERROR));
        $one = $first['transitions'][count($first['transitions']) - 1];
        $two = $second['transitions'][count($second['transitions']) - 1];
        self::assertSame([ScanMeasurements::CONTRACT], $one['contracts']);
        self::assertSame([ScanMeasurements::CONTRACT], $two['contracts']);
        $contract = new ScanMeasurements();
        self::assertSame($contract->comparable($node, $one['native'], false), $contract->comparable($node, $two['native'], false));
    }
}
