<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Flush;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes FlushTables values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables>
 */
final class FlushTablesGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $noWriteToBinlog Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable>>|null $tables Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushLock|null>|null $lock Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $noWriteToBinlog, ?ErisGenerator $tables = null, ?ErisGenerator $lock = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables(noWriteToBinlog: $parts[0], tables: $parts[1], lock: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $noWriteToBinlog,
            $tables ?? Generators::constant([]),
            $lock ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables>>
     */
    public static function forms(): iterable
    {
        yield 'FlushTables/370' => new FlushTablesGenerator(
            noWriteToBinlog: Generators::constant(false),
            tables: Generators::constant([]),
            lock: Generators::constant(null)
        );
        yield 'FlushTables/371' => new FlushTablesGenerator(
            noWriteToBinlog: Generators::constant(false),
            tables: Generators::constant([]),
            lock: FlushLockGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushLock::WithReadLock])
        );
    }

}
