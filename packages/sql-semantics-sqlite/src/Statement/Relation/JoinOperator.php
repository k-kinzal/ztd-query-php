<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Relation;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Rules\ClosedList;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The operator between two terms of a FROM clause: a comma, JOIN, or JOIN after up to three words.
 *
 * Rule: SQLITE-JOIN-OPERATOR-001. The grammar admits one join keyword and
 * then up to two arbitrary names before JOIN; SQLite accepts the words only
 * when each is a join keyword and the combination is meaningful. A word that
 * is no bare join keyword is kept as the name written. NATURAL merges the
 * common columns; LEFT, RIGHT and FULL select the sides whose rows are
 * extended with NULLs (LEFT and RIGHT together are FULL); INNER or CROSS
 * with an outer keyword, OUTER alone, and any other word are an unknown join
 * type, which SQLite reports and treats as an inner join.
 * Source: https://sqlite.org/lang_select.html#the_from_clause. Status: Implemented.
 *
 * @visibility public
 * @example Reading the meaning of a join operator
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 FROM t NATURAL LEFT JOIN u');
 *     $operator = $query->statement->from->steps[0]->operator;
 *     [$operator->natural(), $operator->left(), $operator->right(), $operator->valid()] // => [true, true, false, true]
 * @example Refusing words after a comma
 *     new \SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOperator(true, [\SqlSemantics\Platform\Sqlite\Statement\Relation\JoinKeyword::Left]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class JoinOperator implements Node
{
    use Snapshot;

    /**
     * @var list<JoinKeyword|Name> The words before JOIN in written order
     */
    public readonly array $words;

    /**
     * @param bool $comma Whether the operator is a comma
     * @param list<JoinKeyword|Name> $words The words before JOIN: a join keyword first, then up to two keywords or names
     * @throws InvalidConstruction When the words are not what the grammar admits before JOIN
     */
    public function __construct(public readonly bool $comma = false, array $words = [])
    {
        $list = (new ClosedList())->of($words, [JoinKeyword::class, Name::class], 'A join word is a join keyword or a name.');
        Check::input(count($list) <= 3 && ($list === [] || (!$comma && $list[0] instanceof JoinKeyword)), 'A join operator is a comma, JOIN, or JOIN after a join keyword and up to two more words.');
        $this->words = $list;
    }

    /**
     * Tells whether every word is a join keyword and the combination is one SQLite accepts.
     */
    public function valid(): bool
    {
        $outer = false;
        $side = false;
        $inner = false;
        foreach ($this->words as $word) {
            if ($word instanceof Name) {
                return false;
            }
            $outer = $outer || in_array($word, [JoinKeyword::Outer, JoinKeyword::Left, JoinKeyword::Right, JoinKeyword::Full], true);
            $side = $side || in_array($word, [JoinKeyword::Left, JoinKeyword::Right, JoinKeyword::Full], true);
            $inner = $inner || $word === JoinKeyword::Inner || $word === JoinKeyword::Cross;
        }

        return !($inner && $outer) && ($side || !$outer);
    }

    /**
     * Tells whether the join merges the columns common to both sides.
     */
    public function natural(): bool
    {
        return $this->valid() && in_array(JoinKeyword::Natural, $this->words, true);
    }

    /**
     * Tells whether the rows of the right side can be extended with NULLs.
     */
    public function left(): bool
    {
        return $this->valid() && (in_array(JoinKeyword::Left, $this->words, true) || in_array(JoinKeyword::Full, $this->words, true));
    }

    /**
     * Tells whether the rows of the left side can be extended with NULLs.
     */
    public function right(): bool
    {
        return $this->valid() && (in_array(JoinKeyword::Right, $this->words, true) || in_array(JoinKeyword::Full, $this->words, true));
    }

    /**
     * Writes the operator.
     */
    public function render(Output $out): void
    {
        if ($this->comma) {
            $out->symbol(',');

            return;
        }
        foreach ($this->words as $word) {
            if ($word instanceof Name) {
                $out->name($word, NameUse::Label);
            } else {
                $out->keyword($word->value);
            }
        }
        $out->keyword('JOIN');
    }
}
