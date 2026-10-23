<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Hint\Form;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Hint\HintForm;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;
use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;
use SqlSemantics\Statement\Snapshot;

/**
 * A subquery hint: an optional query block and the strategies it enables or disables.
 *
 * SEMIJOIN and NO_SEMIJOIN take any of DUPSWEEDOUT, FIRSTMATCH, LOOSESCAN
 * and MATERIALIZATION, or none; SUBQUERY takes exactly one of INTOEXISTS
 * and MATERIALIZATION. Strategy names are keywords, kept in upper case.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-subquery.
 *
 * @visibility public
 * @example Writing a semijoin hint
 *     (new \SqlSemantics\Platform\MySql\Statement\Hint\Form\StrategyHint(\SqlSemantics\Platform\MySql\Statement\Hint\HintName::Semijoin, 'qb', ['FIRSTMATCH', 'LOOSESCAN']))->text() // => 'SEMIJOIN(@`qb` FIRSTMATCH, LOOSESCAN)'
 */
final class StrategyHint implements OptimizerHint
{
    use Snapshot;

    /**
     * The strategies SEMIJOIN and NO_SEMIJOIN take.
     */
    public const SEMIJOIN = ['DUPSWEEDOUT', 'FIRSTMATCH', 'LOOSESCAN', 'MATERIALIZATION'];

    /**
     * The strategies SUBQUERY takes.
     */
    public const SUBQUERY = ['INTOEXISTS', 'MATERIALIZATION'];

    /**
     * @var list<string> The strategies, in written order and upper case
     */
    public readonly array $strategies;

    /**
     * @param HintName $hint The name of the hint
     * @param string|null $block The query block written after `@`, or null for the block of the hint
     * @param list<string> $strategies The strategies, in written order and upper case
     */
    public function __construct(public readonly HintName $hint, public readonly ?string $block, array $strategies)
    {
        $form = $hint->form();
        Check::input($form === HintForm::Semijoin || $form === HintForm::Subquery, 'A strategy hint is a subquery hint.');
        $list = [];
        foreach ($strategies as $strategy) {
            Check::input(in_array($strategy, $form === HintForm::Subquery ? self::SUBQUERY : self::SEMIJOIN, true), 'A strategy of a subquery hint is one its hint takes.');
            $list[] = $strategy;
        }
        $this->strategies = $list;
        Check::input($form !== HintForm::Subquery || count($list) === 1, 'SUBQUERY names one strategy.');
        Check::input($block !== '', 'A query block has a name.');
    }

    /**
     * Answers the name of the hint.
     */
    public function name(): HintName
    {
        return $this->hint;
    }

    /**
     * Answers the hint as it is written in a hint comment, with every name quoted.
     */
    public function text(): string
    {
        $head = $this->block === null ? '' : '@' . HintTable::quote($this->block) . ($this->strategies === [] ? '' : ' ');

        return $this->hint->value . '(' . $head . implode(', ', $this->strategies) . ')';
    }
}
