<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Audit\Process;

use Fuzz\Structure\Audit\SessionSetup;
use Fuzz\Structure\Execution;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowProcesslist;

/**
 * Demonstrates that process text is runtime metadata rather than execution input.
 */
#[CoversNothing]
#[Medium]
final class RequestContextTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function providerLabels(): iterable
    {
        foreach ([false, true] as $full) {
            foreach (['invalid request label', str_repeat('あ', 140)] as $label) {
                yield (int) $full . ':' . strlen($label) => [$full, $label];
            }
        }
    }

    /**
     * Reuses one object with arbitrary request labels, including non-SQL multibyte text.
     */
    #[DataProvider('providerLabels')]
    public function testSameStructureObservesRuntimeLabelsWithoutParsing(bool $full, string $label): void
    {
        $session = (new Instance('5.6.51'))->connect();
        (new SessionSetup())->apply($session, '');
        $node = new ShowProcesslist($full);
        $session->text = $label;
        $first = (new Execution())->runIn($session, $node);
        $session->text = 'another invalid label';
        $second = (new Execution())->runIn($session, $node);

        self::assertInstanceOf(ResultSet::class, $first);
        self::assertInstanceOf(ResultSet::class, $second);
        self::assertSame($full ? $label : mb_substr($label, 0, 100), $first->rows[0][7]);
        self::assertSame('another invalid label', $second->rows[0][7]);
    }
}
