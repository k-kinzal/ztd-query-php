<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Insert;

use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\AnalyzedStatement;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Source\Span;

/**
 * An INSERT of VALUES with types and destination references owned by its SQL elements.
 *
 * This statement is constructed without an analysis environment, facts index,
 * original SQL or parser tree. REPLACE is a different SQL statement.
 *
 * @visibility public
 * @example Reconstructing the selected INSERT syntax
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("INSERT t VALUE ('42')");
 *     $insert->toString() // => "INSERT t VALUE ('42')"
 */
final class InsertStatement implements AnalyzedStatement, Node
{
    use Snapshot;

    /**
     * @param LanguageProfile $profile The release and lexical settings for reconstruction
     * @param InsertTarget $target The insertion destination
     * @param ValuesClause $values The values before destination conversion
     * @param Charset|null $client The client encoding for reconstructed identifiers
     * @param Span|null $source The original statement location
     */
    public function __construct(public readonly LanguageProfile $profile, public readonly InsertTarget $target, public readonly ValuesClause $values, public readonly ?Charset $client = null, public readonly ?Span $source = null, public readonly bool $intoWritten = true)
    {
        Check::input($profile->grammar->database() === 'mysql', 'A MySQL INSERT uses a MySQL language profile.');
        if ($target->columns !== null && $target->columns !== []) {
            Check::input(count($target->columns) === count($values->rows[0]), 'Each VALUES row matches the explicit destination count.');
        }
    }

    /**
     * Writes INSERT entirely from its semantic fields.
     */
    public function render(Output $out): void
    {
        $out->keyword('INSERT');
        if ($this->intoWritten) {
            $out->keyword('INTO');
        }
        $out->node($this->target)->node($this->values);
    }

    /**
     * Reconstructs the SQL without an input string or source text lookup.
     */
    public function toString(): string
    {
        $out = new Output(new Codec($this->profile->grammar, $this->profile->lexical, $this->client));
        $this->render($out);
        return (new Lexical())->join($out->pieces()) . $out->trailing();
    }

    /**
     * Answers the statement's selected language profile.
     */
    public function profile(): LanguageProfile
    {
        return $this->profile;
    }

    /**
     * INSERT introduces no schema declarations.
     *
     * @return list<\SqlSemantics\Statement\Declaration\Table>
     */
    public function declarations(): array
    {
        return [];
    }
}
