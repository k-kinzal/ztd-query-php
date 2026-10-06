<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * One option of EXPLAIN, VACUUM, ANALYZE, CLUSTER or REINDEX: a name and an optional value.
 *
 * Mirrors PostgreSQL's `DefElem` of `utility_option_elem`. The grammar
 * accepts any word as the name and a word, a string, a number or nothing as
 * the value; the command decides which names it knows. A name is a word, or
 * one of the keywords the grammar reads on their own; the keyword FORMAT is
 * read only directly before the word `json`.
 * Source: https://www.postgresql.org/docs/17/sql-explain.html, https://www.postgresql.org/docs/17/sql-vacuum.html.
 *
 * @visibility public
 * @example Reading an option and its value
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('VACUUM (PARALLEL 2, ANALYSE)');
 *     [$operation->statement->options[0]->option(), $operation->statement->options[0]->argument->magnitude->digits, $operation->statement->options[1]->option()] // => ['parallel', '2', 'analyze']
 * @example Refusing the FORMAT keyword before another value than json
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\UtilityOption(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionKeyword::Format) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class UtilityOption implements Clause
{
    use Snapshot;

    /**
     * @param Name|OptionKeyword $name The option name: a word, or a keyword the grammar reads on its own
     * @param Word|StringConstant|Toggle|SignedNumber|null $argument The value, when one is written: a word, a string, TRUE, FALSE or ON, or a number
     */
    public function __construct(public readonly Name|OptionKeyword $name, public readonly Word|StringConstant|Toggle|SignedNumber|null $argument = null)
    {
        Check::input($name !== OptionKeyword::Format || ($argument instanceof Word && $argument->word->value === 'json'), 'The FORMAT keyword is read only before the word json.');
    }

    /**
     * Answers the option name as the command receives it.
     */
    public function option(): string
    {
        return $this->name instanceof Name ? $this->name->value : $this->name->option();
    }

    /**
     * Derives the value.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->argument?->deriveClause($derivation, $environment);
    }

    /**
     * Writes the name and the value; the word `format` before the word `json` is quoted so that it is not read as the keyword.
     */
    public function render(Output $out): void
    {
        if ($this->name instanceof OptionKeyword) {
            $out->keyword($this->name->value);
        } else {
            $keyword = $this->name->value === 'format' && $this->argument instanceof Word && $this->argument->word->value === 'json';
            $out->name($this->name, $keyword ? NameUse::Identifier : NameUse::Routine);
        }
        if ($this->argument instanceof Toggle) {
            $out->keyword($this->argument->value);

            return;
        }
        $out->node($this->argument);
    }

    /**
     * Answers the value as the text the command receives, or null when no value is written.
     */
    public function text(): ?string
    {
        $argument = $this->argument;
        return match (true) {
            $argument === null => null,
            $argument instanceof SignedNumber => $argument->text(),
            $argument instanceof Word => $argument->word->value,
            $argument instanceof StringConstant => $argument->value,
            $argument instanceof Toggle => $argument->text(),
        };
    }
}
