<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\AnalyzedStatement;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Source\Span;

/**
 * `RENAME USER old TO new, …`: a request to rename accounts, applied pair by pair in order.
 *
 * Mirrors SQLCOM_RENAME_USER. Rule: MYSQL-RENAME-USER-001. Accounts are not
 * declared in a context, so the statement has no resolution facts. Construction
 * preserves every ordered pair, including contradictory identity requirements.
 * The derived problem describes that intrinsic condition without executing it:
 * catalog lookup, implicit commit and partial legacy effects belong to execution.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/rename-user.html. Status: Implemented.
 *
 * @visibility public
 * @example Renaming two accounts
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('rename user a to b, c to d')->toString() // => 'RENAME USER a TO b, c TO d'
 */
final class RenameUser implements Statement, AnalyzedStatement
{
    use Snapshot;

    /**
     * @var non-empty-list<UserRenaming> The renamings in order
     */
    public readonly array $renamings;

    /**
     * The intrinsic identity contradiction, independently of the runtime account catalog.
     */
    public readonly ?Problem\User\RefusedRenameUser $problem;

    /**
     * @param list<UserRenaming> $renamings The renamings in order; at least one
     * @param LanguageProfile $profile The selected MySQL grammar and lexical interpretation
     * @param Charset|null $client The client encoding used to reconstruct account names
     * @param Span|null $source The optional original statement location
     */
    public function __construct(array $renamings, public readonly LanguageProfile $profile = new LanguageProfile(GrammarRelease::MySql847), public readonly ?Charset $client = null, public readonly ?Span $source = null)
    {
        Check::input($profile->grammar->database() === 'mysql', 'An account rename uses a MySQL language profile.');
        $this->renamings = Check::listOf($renamings, UserRenaming::class, 'RENAME USER names at least one pair.', 1);
        $this->problem = (new \SqlSemantics\Platform\MySql\Rules\Account\Renamings())->problem($this->renamings) === null ? null : new Problem\User\RefusedRenameUser($this->renamings);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives nothing: the statement names no relation and no expression.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('RENAME', 'USER')->list($this->renamings);
    }

    /**
     * Reconstructs the complete request from its ordered pairs and selected language.
     */
    public function toString(): string
    {
        $out = new Output(new \SqlSemantics\Platform\MySql\Rendering\Codec($this->profile->grammar, $this->profile->lexical, $this->client));
        $this->render($out);
        return (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()) . $out->trailing();
    }

    /**
     * Answers the selected language without retaining an analysis environment.
     */
    public function profile(): LanguageProfile
    {
        return $this->profile;
    }

    /**
     * An account rename contributes no table declarations.
     *
     * @return list<\SqlSemantics\Statement\Declaration\Table>
     */
    public function declarations(): array
    {
        return [];
    }
}
