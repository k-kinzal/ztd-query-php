<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Insert;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertStatement;

/**
 * Composes InsertStatement values through Eris generation and shrinking.
 *
 * @implements ErisGenerator<InsertStatement>
 */
final class InsertStatementGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<InsertStatement>
     */
    private ErisGenerator $delegate;

    /**
     * Composes a missing destination and independently generated string values.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release)
    {
        $collation = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::defaults($release)->connection;
        $this->delegate = Generators::map(static function (array $data) use ($release): InsertStatement {
            if (!$data['target'] instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertTarget || !$data['values'] instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesClause || !is_bool($data['into'])) {
                throw new LogicException('The INSERT generators must produce a target and a VALUES clause.');
            }
            return new InsertStatement(new \SqlSemantics\Contract\LanguageProfile($release), $data['target'], $data['values'], intoWritten: $data['into']);
        }, Generators::associative([
            'target' => new InsertTargetGenerator(),
            'values' => new ValuesClauseGenerator(new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator($collation)),
            'into' => Generators::bool(),
        ]));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<InsertStatement>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<InsertStatement>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
