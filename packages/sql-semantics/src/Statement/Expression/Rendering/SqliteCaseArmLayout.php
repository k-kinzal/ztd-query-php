<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Rendering;

use SqlSemantics\Statement\Validation\Check;

/**
 * Bounded WHEN/THEN spelling around the actual two operands of one CASE arm.
 * @visibility public
 * @example Separating keywords even when empty gaps were requested
 *     $layout = new \SqlSemantics\Statement\Expression\Rendering\SqliteCaseArmLayout('when', 'then', '', '', '', '');
 *     $layout->write('1', '2') // => 'when 1 then 2'
 */
final class SqliteCaseArmLayout
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * No operand, extra clause, or unterminated comment is an admissible layout value.
     */
    public function __construct(public readonly string $whenKeyword = 'WHEN', public readonly string $thenKeyword = 'THEN', public readonly string $before = ' ', public readonly string $afterWhen = ' ', public readonly string $beforeThen = ' ', public readonly string $afterThen = ' ')
    {
        Check::input(strtoupper($whenKeyword) === 'WHEN' && strtoupper($thenKeyword) === 'THEN', 'A CASE arm spelling contains only WHEN and THEN.');
        foreach ([$before, $afterWhen, $beforeThen, $afterThen] as $gap) {
            Check::input((new SqliteTrivia())->accepts($gap), 'CASE arm gaps contain only complete trivia.');
        }
    }

    /**
     * Writes the actual test and result without replacing either by stored text.
     */
    public function write(string $test, string $result): string
    {
        $head = SqliteKeywordSpacing::join($this->whenKeyword, $this->afterWhen, $test);
        $head = SqliteKeywordSpacing::join($head, $this->beforeThen, $this->thenKeyword);
        return SqliteKeywordSpacing::join($head, $this->afterThen, $result);
    }

    /**
     * Attaches one constructed branch to its preceding CASE base or result.
     */
    public function append(string $previous, string $branch): string
    {
        return SqliteKeywordSpacing::join($previous, $this->before, $branch);
    }
}
