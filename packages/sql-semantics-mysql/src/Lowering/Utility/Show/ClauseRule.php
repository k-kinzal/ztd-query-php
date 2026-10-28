<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Show;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ProfileSection;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowListing;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers the clauses SHOW statements share.
 *
 * Rule: MYSQL-SHOW-CLAUSE-LOWERING-001. Scope: wild_and_where (5.6),
 * opt_wild_or_where, opt_wild_or_where_for_show (5.7), opt_db, from_or_in,
 * opt_full, opt_show_cmd_type, opt_extended, opt_var_type, engine_or_all,
 * binlog_in, opt_binlog_in, binlog_from, opt_profile_defs, profile_defs,
 * profile_def, opt_profile_args, opt_for_query. FROM and IN are the same
 * keyword and LOCAL retains its spelling and denotes SESSION (UtilityNoise). Constructs:
 * ShowLike, ShowWhere, ShowListing, ProfileSection, VariableScope and the
 * leaf values. Terminates: the profile list spine is flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Utility
 */
final class ClauseRule
{
    /**
     * The empty filter productions.
     */
    private const UNFILTERED = ['wild_and_where:' => true, 'opt_wild_or_where:' => true, 'opt_wild_or_where_for_show:' => true];

    /**
     * The LIKE productions.
     */
    private const LIKE = [
        'wild_and_where: LIKE TEXT_STRING_sys' => true, 'opt_wild_or_where: LIKE TEXT_STRING_sys' => true,
        'opt_wild_or_where_for_show: LIKE TEXT_STRING_sys' => true, 'opt_wild_or_where: LIKE TEXT_STRING_literal' => true,
    ];

    /**
     * The WHERE productions whose condition is an expression at the second position.
     */
    private const WHERE = ['wild_and_where: WHERE expr' => true, 'opt_wild_or_where: WHERE expr' => true, 'opt_wild_or_where_for_show: WHERE expr' => true];

    /**
     * The listing keywords of SHOW TABLES and SHOW COLUMNS.
     */
    private const LISTINGS = [
        'opt_full:' => null, 'opt_full: FULL' => ShowListing::Full, 'opt_show_cmd_type:' => null, 'opt_show_cmd_type: FULL' => ShowListing::Full,
        'opt_show_cmd_type: EXTENDED_SYM' => ShowListing::Extended, 'opt_show_cmd_type: EXTENDED_SYM FULL' => ShowListing::ExtendedFull,
    ];

    /**
     * The scope keywords of SHOW STATUS and SHOW VARIABLES.
     */
    private const SCOPES = [
        'opt_var_type:' => null, 'opt_var_type: GLOBAL_SYM' => VariableScope::Global, 'opt_var_type: LOCAL_SYM' => VariableScope::Local,
        'opt_var_type: SESSION_SYM' => VariableScope::Session,
    ];

    /**
     * The sections of SHOW PROFILE.
     */
    private const SECTIONS = [
        'profile_def: CPU_SYM' => ProfileSection::Cpu, 'profile_def: MEMORY_SYM' => ProfileSection::Memory, 'profile_def: BLOCK_SYM IO_SYM' => ProfileSection::BlockIo,
        'profile_def: CONTEXT_SYM SWITCHES_SYM' => ProfileSection::ContextSwitches, 'profile_def: PAGE_SYM FAULTS_SYM' => ProfileSection::PageFaults,
        'profile_def: IPC_SYM' => ProfileSection::Ipc, 'profile_def: SWAPS_SYM' => ProfileSection::Swaps, 'profile_def: SOURCE_SYM' => ProfileSection::Source,
        'profile_def: ALL' => ProfileSection::All,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a LIKE or WHERE clause: a node of `wild_and_where`, `opt_wild_or_where` or `opt_wild_or_where_for_show`.
     *
     * @throws \SqlSemantics\Construction\RejectedInput When the WHERE operand has no scalar role
     * @throws ImplementationGap When a production has no rule
     */
    public function filter(Node $filter): ShowLike|ShowWhere|null
    {
        $form = $this->lowering->form($filter);
        if (isset(self::UNFILTERED[$form->signature])) {
            return null;
        }
        if (isset(self::LIKE[$form->signature])) {
            return new ShowLike($this->lowering->literals->text($form->node(1)));
        }
        if (isset(self::WHERE[$form->signature])) {
            return new ShowWhere($this->lowering->expressions->expression($form->node(1)));
        }
        if ($form->signature !== 'opt_wild_or_where: where_clause') {
            throw ImplementationGap::production($form);
        }

        return $this->where($form->node(0));
    }

    /**
     * Attaches a filter while preserving its inspection owner if the operand is refused.
     *
     * @throws \SqlSemantics\Construction\RejectedInput When the filter has no scalar role
     */
    public function inspection(\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTableStatus|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowEvents|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionStatus|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowDatabases|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowStatus|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowVariables|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCharacterSet|\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCollation $header, Node $source): \SqlSemantics\Platform\MySql\Statement\Statement
    {
        try {
            $filter = $header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys ? $this->where($source) : $this->filter($source);
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            if (!$failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar) {
                throw $failure;
            }
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Utility\Show\RefusedFilter($header, $failure->problem), $failure->end, $failure->aborts);
        }
        if ($header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys) {
            \SqlSemantics\Diagnostic\Check::invariant(!$filter instanceof ShowLike, 'Index inspections admit only WHERE filters.');
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys($header->table, $header->extended, $header->database, $filter);
        }
        return match (true) {
            $header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowEvents => new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowEvents($header->database, $filter),
            $header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus => new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus($filter),
            $header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionStatus => new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionStatus($filter),
            $header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowDatabases => new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowDatabases($filter),
            $header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables => new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables($header->database, $filter),
            $header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowStatus => new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowStatus($header->scope, $filter),
            $header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowVariables => new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowVariables($header->scope, $filter),
            $header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCharacterSet => new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCharacterSet($filter),
            $header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCollation => new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCollation($filter),
            $header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns => new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns($header->table, $header->listing, $header->database, $filter),
            $header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables => new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables($header->listing, $header->database, $filter),
            $header instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers => new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers($header->full, $header->database, $filter),
            default => new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTableStatus($header->database, $filter),
        };
    }

    /**
     * Lowers a WHERE clause of the query family: a node of `where_clause` or `opt_where_clause`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function where(Node $clause): ?ShowWhere
    {
        $condition = $this->lowering->queries->where($clause);

        return $condition === null ? null : new ShowWhere($condition);
    }

    /**
     * Lowers the database clause: a node of `opt_db`.
     *
     * @param bool $listingFilter Whether the operand filters open-table entries rather than naming a database
     * @throws \SqlSemantics\Construction\RejectedInput When an explicit database name is empty
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function database(Node $database, bool $listingFilter = false): ?Name
    {
        $form = $this->lowering->form($database);
        if ($form->signature === 'opt_db:') {
            return null;
        }
        if ($form->signature !== 'opt_db: from_or_in ident') {
            throw ImplementationGap::production($form);
        }
        $this->preposition($form->node(0));

        $name = $this->lowering->names->identifier($form->node(1));
        if (!$listingFilter && $name->value === '') {
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName(\SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind::Database), (new \SqlSemantics\Construction\SourceRanges())->span($database)[1] ?? 0, false);
        }

        return $name;
    }

    /**
     * Confirms that a node is `from_or_in`, whose FROM and IN are the same keyword.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function preposition(Node $preposition): void
    {
        $form = $this->lowering->form($preposition);
        if ($form->signature !== 'from_or_in: FROM' && $form->signature !== 'from_or_in: IN_SYM') {
            throw ImplementationGap::production($form);
        }
    }

    /**
     * Lowers the FULL and EXTENDED keywords: a node of `opt_full` or `opt_show_cmd_type`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function listing(Node $listing): ?ShowListing
    {
        $form = $this->lowering->form($listing);

        return array_key_exists($form->signature, self::LISTINGS) ? self::LISTINGS[$form->signature] : throw ImplementationGap::production($form);
    }

    /**
     * Lowers the optional EXTENDED keyword of SHOW INDEX: a node of `opt_extended`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function extended(Node $extended): bool
    {
        $form = $this->lowering->form($extended);

        return match ($form->signature) {
            'opt_extended:' => false,
            'opt_extended: EXTENDED_SYM' => true,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the scope keyword of SHOW STATUS and SHOW VARIABLES: a node of `opt_var_type`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function scope(Node $scope): ?VariableScope
    {
        $form = $this->lowering->form($scope);

        return array_key_exists($form->signature, self::SCOPES) ? self::SCOPES[$form->signature] : throw ImplementationGap::production($form);
    }

    /**
     * Lowers the engine of SHOW ENGINE in MySQL 8.0 and later, null for ALL: a node of `engine_or_all`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function engine(Node $engine): ?Name
    {
        $form = $this->lowering->form($engine);

        return match ($form->signature) {
            'engine_or_all: ident_or_text' => $this->lowering->names->identifier($form->node(0)),
            'engine_or_all: ALL' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the log file of SHOW BINLOG EVENTS and SHOW RELAYLOG EVENTS: a node of `binlog_in` or `opt_binlog_in`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function file(Node $file): ?Text
    {
        $form = $this->lowering->form($file);

        return match ($form->signature) {
            'binlog_in:', 'opt_binlog_in:' => null,
            'binlog_in: IN_SYM TEXT_STRING_sys', 'opt_binlog_in: IN_SYM TEXT_STRING_sys' => $this->lowering->literals->text($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the position of SHOW BINLOG EVENTS and SHOW RELAYLOG EVENTS: a node of `binlog_from`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function position(Node $position): ?Numeral
    {
        $form = $this->lowering->form($position);

        return match ($form->signature) {
            'binlog_from:' => null,
            'binlog_from: FROM ulonglong_num' => $this->lowering->numbers->numeral($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the sections of SHOW PROFILE in the order written: a node of `opt_profile_defs`.
     *
     * @return list<ProfileSection>
     * @throws ImplementationGap When a production has no rule
     */
    public function sections(Node $sections): array
    {
        $form = $this->lowering->form($sections);
        if ($form->signature === 'opt_profile_defs:') {
            return [];
        }
        if ($form->signature !== 'opt_profile_defs: profile_defs') {
            throw ImplementationGap::production($form);
        }
        for ($spine = $form->node(0); $spine instanceof Node && $spine->name === 'profile_defs'; $spine = $spine->children[0]) {
            $link = $this->lowering->form($spine);
            if ($link->signature !== 'profile_defs: profile_def' && $link->signature !== 'profile_defs: profile_defs , profile_def') {
                throw ImplementationGap::production($link);
            }
        }
        $lowered = [];
        foreach ((new Lists())->items($form->node(0)) as $definition) {
            $section = $this->lowering->form($definition);
            $lowered[] = self::SECTIONS[$section->signature] ?? throw ImplementationGap::production($section);
        }

        return $lowered;
    }

    /**
     * Lowers the statement number of SHOW PROFILE: a node of `opt_profile_args` or `opt_for_query`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function query(Node $query): ?Numeral
    {
        $form = $this->lowering->form($query);

        return match ($form->signature) {
            'opt_profile_args:', 'opt_for_query:' => null,
            'opt_profile_args: FOR_SYM QUERY_SYM NUM', 'opt_for_query: FOR_SYM QUERY_SYM NUM' => $this->lowering->numbers->token($form->token(2)),
            default => throw ImplementationGap::production($form),
        };
    }
}
