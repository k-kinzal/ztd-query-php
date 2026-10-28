<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Flush;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Flush values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush>
 */
final class FlushGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\BinlogOption|null> $binlog Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushItem>> $items Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $binlog, ErisGenerator $items, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush(binlog: $parts[0], items: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $binlog,
            $items,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Flush\Flush>>
     */
    public static function forms(): iterable
    {
        yield 'Flush/45' => new FlushGenerator(
            binlog: \Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator::optional(),
            items: \Tests\Generator\Operands::tuple(
                new FlushItemGenerator(
                    option: FlushOptionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushOption::Privileges]),
                    channel: Generators::constant(null)
                )
            )
        );
    }

}
