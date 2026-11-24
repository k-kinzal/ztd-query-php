"""Reproduce SQLite evidence using recorded PHP responses and a fresh database."""

import json
import tempfile
from pathlib import Path
from types import SimpleNamespace

import run
import run_sqlite_add_column as add_column
import run_sqlite_add_generated_column as add_generated_column
import run_sqlite_drop_column as drop_column
import run_sqlite_drop_index as drop_index
import run_sqlite_constraint_index_names as constraint_indexes
import run_sqlite_reindex as reindex
import run_sqlite_analyze_statistics as analyze_statistics
import run_sqlite_flag_pragmas as flag_pragmas
import run_sqlite_table_metadata as table_metadata
import run_sqlite_inventory as inventory
import run_sqlite_index_metadata as index_metadata
import run_sqlite_fk_audit as fk_audit
import run_sqlite_create_index as create_index
import run_sqlite_index_expression_spelling as index_spelling
import run_sqlite_appended_checks as appended_checks
import run_sqlite_appended_implicit_checks as appended_implicit_checks
import run_sqlite_auto_rowid as auto_rowid
import run_sqlite_case as choice
import run_sqlite_coalesce as coalesce
import run_sqlite_cast as cast
import run_sqlite_compound as compound
import run_sqlite_computed_defaults as computed_defaults
import run_sqlite_create_table as create
import run_sqlite_create_as as create_as
import run_sqlite_create_view as create_view
import run_sqlite_create_view_grouped as create_view_grouped
import run_sqlite_create_view_compound as create_view_compound
import run_sqlite_create_view_local as create_view_local
import run_sqlite_resolved_view_input as view_input
import run_sqlite_delete as delete
import run_sqlite_delete_returning as returning
import run_sqlite_defaults as defaults
import run_sqlite_drop_table as drop
import run_sqlite_drop_view as drop_view
import run_sqlite_group_filter as group_filter
import run_sqlite_grouped as grouped
import run_sqlite_insert as insert
import run_sqlite_insert_input as insert_input
import run_sqlite_insert_query as insert_query
import run_sqlite_insert_returning as insert_returning
import run_sqlite_local_source as local
import run_sqlite_joined_local_source as joined_local
import run_sqlite_not_null_conflict as not_null_conflict
import run_sqlite_numeric as numeric
import run_sqlite_mutation_conflicts as mutation_conflicts
import run_sqlite_pattern as pattern
import run_sqlite_primary_keys as primary_keys
import run_sqlite_recursive_source as recursive
import run_sqlite_relational as relational
import run_sqlite_merged_join as merged_join
import run_sqlite_merged_right_join as merged_right
import run_sqlite_merged_full_join as merged_full
import run_sqlite_merged_prefix_join as merged_prefix
import run_sqlite_result as result
import run_sqlite_scalars as scalars
import run_sqlite_transaction as transaction
import run_sqlite_concatenation as concatenation
import run_sqlite_collation as collation
import run_sqlite_folded_membership as folded_membership
import run_sqlite_membership as membership
import run_sqlite_between as between
import run_sqlite_strict_tables as strict_tables
import run_sqlite_truth as truth
import run_sqlite_unary as unary
import run_sqlite_unique_keys as unique_keys
import run_sqlite_named_checks as named_checks
import run_sqlite_folded_checks as folded_checks
import run_sqlite_implicit_checks as implicit_checks
import run_sqlite_generated_columns as generated_columns
import run_sqlite_rename as rename
import run_sqlite_update as update
import run_sqlite_upsert_nothing as upsert_nothing
import run_sqlite_update_returning as update_returning
import run_sqlite_values as values
import run_sqlite_wildcard as wildcard
from cases import SCHEMA
from evidence_transcript import Transcript, canonical, require_reproduced
from replay_sqlite_faults import audit_initial, contract as fault_contract, replay_registered, replay_relational
from run import TOOL, require
from sqlite_engine import Database

DOMAINS = {
    choice.CONTRACT_NAME: (choice, choice.FIXTURE),
    coalesce.CONTRACT_NAME: (coalesce, coalesce.FIXTURE),
    cast.CONTRACT_NAME: (cast, cast.FIXTURE),
    defaults.CONTRACT_NAME: (defaults, defaults.FIXTURE),
    computed_defaults.CONTRACT_NAME: (computed_defaults, computed_defaults.FIXTURE),
    not_null_conflict.CONTRACT_NAME: (not_null_conflict, not_null_conflict.FIXTURE),
    returning.CONTRACT_NAME: (returning, returning.SCHEMA),
    "sqlite-relational-v4.json": (relational, relational.SCHEMA),
    merged_join.CONTRACT_NAME: (merged_join, merged_join.FIXTURE),
    merged_right.CONTRACT_NAME: (merged_right, merged_right.FIXTURE),
    merged_full.CONTRACT_NAME: (merged_full, merged_full.FIXTURE),
    merged_prefix.CONTRACT_NAME: (merged_prefix, merged_prefix.FIXTURE),
    "sqlite-result-v1.json": (result, result.SCHEMA + result.PAGE_DDL + ";" + result.PAGE_ROWS),
    "sqlite-delete-v1.json": (delete, delete.SCHEMA),
    "sqlite-values-v1.json": (values, ""),
    "sqlite-local-source-v2.json": (local, local.DATA),
    joined_local.CONTRACT_NAME: (joined_local, joined_local.FIXTURE),
    recursive.CONTRACT_NAME: (recursive, recursive.FIXTURE),
    "sqlite-insert-v1.json": (insert, insert.FIXTURE),
    insert_query.CONTRACT_NAME: (insert_query, insert_query.FIXTURE),
    insert_returning.CONTRACT_NAME: (insert_returning, insert_returning.FIXTURE),
    insert_input.CONTRACT_NAME: (insert_input, insert_input.FIXTURE),
    "sqlite-update-v1.json": (update, update.FIXTURE),
    update_returning.CONTRACT_NAME: (update_returning, update_returning.FIXTURE),
    "sqlite-drop-table-v2.json": (drop, drop.FIXTURE),
    drop_view.CONTRACT_NAME: (drop_view, drop_view.FIXTURE),
    "sqlite-create-table-v3.json": (create, create.FIXTURE),
    create_as.CONTRACT_NAME: (create_as, create_as.FIXTURE),
    create_view.CONTRACT_NAME: (create_view, create_view.FIXTURE),
    create_view_grouped.CONTRACT_NAME: (create_view_grouped, create_view_grouped.FIXTURE),
    create_view_compound.CONTRACT_NAME: (create_view_compound, create_view_compound.FIXTURE),
    create_view_local.CONTRACT_NAME: (create_view_local, create_view_local.FIXTURE),
    view_input.CONTRACT_NAME: (view_input, view_input.FIXTURE),
    strict_tables.CONTRACT_NAME: (strict_tables, strict_tables.FIXTURE),
    primary_keys.CONTRACT_NAME: (primary_keys, primary_keys.FIXTURE),
    rename.CONTRACT_NAME: (rename, rename.FIXTURE),
    add_column.CONTRACT_NAME: (add_column, add_column.FIXTURE),
    appended_checks.CONTRACT_NAME: (appended_checks, appended_checks.FIXTURE),
    appended_implicit_checks.CONTRACT_NAME: (appended_implicit_checks, appended_implicit_checks.FIXTURE),
    add_generated_column.CONTRACT_NAME: (add_generated_column, add_generated_column.FIXTURE),
    drop_column.CONTRACT_NAME: (drop_column, drop_column.FIXTURE),
    drop_index.CONTRACT_NAME: (drop_index, drop_index.FIXTURE),
    constraint_indexes.CONTRACT_NAME: (constraint_indexes, constraint_indexes.FIXTURE),
    reindex.CONTRACT_NAME: (reindex, reindex.FIXTURE),
    analyze_statistics.CONTRACT_NAME: (analyze_statistics, analyze_statistics.FIXTURE),
    flag_pragmas.CONTRACT_NAME: (flag_pragmas, flag_pragmas.FIXTURE),
    table_metadata.CONTRACT_NAME: (table_metadata, table_metadata.FIXTURE),
    inventory.CONTRACT_NAME: (inventory, inventory.FIXTURE),
    index_metadata.CONTRACT_NAME: (index_metadata, index_metadata.FIXTURE),
    fk_audit.CONTRACT_NAME: (fk_audit, fk_audit.FIXTURE),
    create_index.CONTRACT_NAME: (create_index, create_index.FIXTURE),
    index_spelling.CONTRACT_NAME: (index_spelling, index_spelling.FIXTURE),
    folded_checks.CONTRACT_NAME: (folded_checks, folded_checks.FIXTURE),
    implicit_checks.CONTRACT_NAME: (implicit_checks, implicit_checks.FIXTURE),
    generated_columns.CONTRACT_NAME: (generated_columns, generated_columns.FIXTURE),
    named_checks.CONTRACT_NAME: (named_checks, named_checks.FIXTURE),
    unique_keys.CONTRACT_NAME: (unique_keys, unique_keys.FIXTURE),
    auto_rowid.CONTRACT_NAME: (auto_rowid, auto_rowid.FIXTURE),
    "sqlite-scalars-v2.json": (scalars, scalars.FIXTURE),
    numeric.CONTRACT_NAME: (numeric, numeric.FIXTURE),
    pattern.CONTRACT_NAME: (pattern, pattern.FIXTURE),
    mutation_conflicts.CONTRACT_NAME: (mutation_conflicts, mutation_conflicts.FIXTURE),
    upsert_nothing.CONTRACT_NAME: (upsert_nothing, upsert_nothing.FIXTURE),
    unary.CONTRACT_NAME: (unary, unary.FIXTURE),
    concatenation.CONTRACT_NAME: (concatenation, concatenation.FIXTURE),
    collation.CONTRACT_NAME: (collation, collation.FIXTURE),
    folded_membership.CONTRACT_NAME: (folded_membership, folded_membership.FIXTURE),
    membership.CONTRACT_NAME: (membership, membership.FIXTURE),
    between.CONTRACT_NAME: (between, between.FIXTURE),
    truth.CONTRACT_NAME: (truth, truth.FIXTURE),
    "sqlite-compound-v1.json": (compound, compound.FIXTURE),
    grouped.CONTRACT_NAME: (grouped, grouped.FIXTURE),
    group_filter.CONTRACT_NAME: (group_filter, group_filter.FIXTURE),
    wildcard.CONTRACT_NAME: (wildcard, wildcard.SCHEMA),
    "sqlite-transaction-v1.json": (transaction, transaction.FIXTURE),
}


def replay(directory, contract_name, index, library, *, candidate=None, manifest=None):
    contract = json.loads((TOOL / contract_name).read_text())
    path = directory / "events.jsonl"
    checks = index["checks"]
    initial = contract_name == "contract-v1.json"
    transcript = Transcript(path, checks.get("php", checks.get("candidateEnvironment")))

    def worker_factory(*_):
        return transcript

    module, fixture = (None, SCHEMA) if initial else DOMAINS[contract_name]
    factory = {recursive: recursive.RecursiveDatabase, grouped: grouped.GroupedDatabase, pattern: pattern.PatternDatabase,
               group_filter: grouped.GroupedDatabase, truth: grouped.GroupedDatabase,
               strict_tables: strict_tables.StrictDatabase, primary_keys: primary_keys.KeyDatabase,
               auto_rowid: primary_keys.KeyDatabase, unique_keys: unique_keys.UniqueDatabase,
               named_checks: named_checks.NamedCheckDatabase, folded_checks: folded_checks.FoldedCheckDatabase,
               implicit_checks: implicit_checks.ImplicitCheckDatabase,
               generated_columns: generated_columns.GeneratedColumnDatabase,
               rename: rename.RenameDatabase, add_column: add_column.AddColumnDatabase, drop_view: drop_view.DropViewDatabase,
               add_generated_column: add_generated_column.GeneratedAddDatabase,
               drop_column: drop_column.DropDatabase,
               drop_index: drop_index.DropIndexDatabase,
               constraint_indexes: constraint_indexes.ConstraintIndexDatabase,
               reindex: reindex.ReindexDatabase,
               analyze_statistics: analyze_statistics.StatisticsDatabase,
               flag_pragmas: flag_pragmas.FlagDatabase,
               table_metadata: table_metadata.TableMetadataDatabase,
               inventory: inventory.InventoryDatabase,
               index_metadata: index_metadata.IndexMetadataDatabase,
               fk_audit: fk_audit.AuditDatabase,
               create_index: create_index.IndexDatabase,
               index_spelling: index_spelling.SpellingIndexDatabase,
               appended_checks: appended_checks.CheckedAdditionDatabase,
               appended_implicit_checks: appended_implicit_checks.ImplicitAdditionDatabase,
               computed_defaults: computed_defaults.DefaultDatabase,
               create_view: create_view.CreateViewDatabase,
               create_view_grouped: create_view_grouped.GroupedViewDatabase,
               create_view_compound: create_view_compound.CreateViewDatabase,
               create_view_local: create_view_local.LocalViewDatabase,
               view_input: view_input.ViewInputDatabase, merged_full: merged_full.FullMergeDatabase,
               joined_local: joined_local.JoinedLocalDatabase, coalesce: coalesce.CoalesceDatabase}.get(module, Database)
    database = factory(library)
    try:
        require(database.version == "3.47.2" and database.source == index["engine"]["source"]
                and database.digest == index["engine"]["libraryDigest"], "evidence-replay-engine-mismatch", contract_name)
        require(index["engine"].get("observerPolicy") == database.policy_digest, "evidence-observer-policy-mismatch", contract_name)
        require(index["engine"].get("textObservation") == database.text_observation_digest,
                "evidence-observer-text-mismatch", contract_name)
        if fixture:
            database.execute(fixture)
        with tempfile.TemporaryDirectory(prefix="semantics-ir-replay-") as temporary:
            output = Path(temporary)
            args = SimpleNamespace(out=output)
            emitted = output / "events.jsonl"
            if initial:
                regenerated = run.run(args, database, contract, emitted, worker_factory)
            elif module is relational:
                regenerated = relational.execute(args, contract, database, emitted, worker_factory)
            else:
                regenerated = module.execute(args, contract, database, worker_factory)
            require_reproduced(path, emitted)
        # JSON object keys in the generated histograms can be ints before serialization.
        serialized = json.loads(json.dumps(regenerated))
        require(canonical(serialized) == canonical(checks), "evidence-summary-mismatch", contract_name)
        faults = {}
        if candidate is not None:
            fault_contract()
            records = index.get("faults", index.get("mutations"))
            args = SimpleNamespace(directory=directory, candidate=candidate, manifest=manifest, records=records)
            if initial:
                faults = audit_initial(directory, records, candidate, manifest)
            elif module is relational:
                faults = replay_relational(args, database, module.inspect_response)
            else:
                faults = module.check_faults(args, database, runner=replay_registered)
        return transcript.consumed, faults
    finally:
        database.close(database.db)
