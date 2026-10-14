<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Expression;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseReadOnly;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Ordered option diagnoses depend on the constructed operands, without session SQL text.
 */
#[CoversNothing]
#[Medium]
final class DatabaseConflictTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerOptions(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Generated repeated spellings retain a conflict's order against a second invalid option.
     */
    #[DataProvider('providerOptions')]
    public function testDirectOptionsRetainTheirFirstFailureAndPrecedingWarning(string $release, int $seed): void
    {
        $choices = new Choices('database-conflicts:' . $seed);
        $first = $choices->one([false, true]);
        $options = array_map(static fn (int $index): DatabaseReadOnly => new DatabaseReadOnly(new Numeral(str_repeat('0', $index) . ($first ? '1' : '0'))), range(0, $choices->integer(1, 5)));
        $options[] = new DatabaseReadOnly($first ? null : new Numeral('1e0'));
        $invalid = new DatabaseEncryption(new Text('bad'));
        $earlier = $seed % 2 === 0;
        $options = $earlier ? [$invalid, ...$options] : [...$options, $invalid];
        $options = [new DatabaseCharset(new CharsetName(new Name('utf8'))), ...$options];
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $error = null;
        try {
            (new Execution())->runIn($session, new AlterDatabase(new Name('missing_database'), $options));
        } catch (SqlError $caught) {
            $error = $caught;
        }

        self::assertSame($earlier ? 1525 : 1302, $error?->getCode());
        self::assertSame($earlier ? "Incorrect argument (should be Y or N) value: 'bad'" : "Conflicting declarations: 'READ ONLY=0' and 'READ ONLY=1'", $error->getMessage());
        self::assertSame([3719, $earlier ? 1525 : 1302], array_column($session->diagnostics->conditions, 1));
    }
}
