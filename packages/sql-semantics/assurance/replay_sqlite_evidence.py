"""Reproduce SQLite evidence using recorded PHP responses and a fresh database."""

import json
import tempfile
from pathlib import Path
from types import SimpleNamespace

import run
import run_sqlite_auto_rowid as auto_rowid
import run_sqlite_case as choice
import run_sqlite_cast as cast
import run_sqlite_compound as compound
import run_sqlite_create_table as create
import run_sqlite_create_as as create_as
import run_sqlite_delete as delete
import run_sqlite_delete_returning as returning
import run_sqlite_defaults as defaults
import run_sqlite_drop_table as drop
import run_sqlite_group_filter as group_filter
import run_sqlite_grouped as grouped
import run_sqlite_insert as insert
import run_sqlite_insert_input as insert_input
import run_sqlite_insert_query as insert_query
import run_sqlite_insert_returning as insert_returning
import run_sqlite_local_source as local
import run_sqlite_not_null_conflict as not_null_conflict
import run_sqlite_numeric as numeric
import run_sqlite_mutation_conflicts as mutation_conflicts
import run_sqlite_pattern as pattern
import run_sqlite_primary_keys as primary_keys
import run_sqlite_recursive_source as recursive
import run_sqlite_relational as relational
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
    cast.CONTRACT_NAME: (cast, cast.FIXTURE),
    defaults.CONTRACT_NAME: (defaults, defaults.FIXTURE),
    not_null_conflict.CONTRACT_NAME: (not_null_conflict, not_null_conflict.FIXTURE),
    returning.CONTRACT_NAME: (returning, returning.SCHEMA),
    "sqlite-relational-v1.json": (relational, relational.SCHEMA),
    "sqlite-result-v1.json": (result, result.SCHEMA + result.PAGE_DDL + ";" + result.PAGE_ROWS),
    "sqlite-delete-v1.json": (delete, delete.SCHEMA),
    "sqlite-values-v1.json": (values, ""),
    "sqlite-local-source-v1.json": (local, local.DATA),
    recursive.CONTRACT_NAME: (recursive, recursive.FIXTURE),
    "sqlite-insert-v1.json": (insert, insert.FIXTURE),
    insert_query.CONTRACT_NAME: (insert_query, insert_query.FIXTURE),
    insert_returning.CONTRACT_NAME: (insert_returning, insert_returning.FIXTURE),
    insert_input.CONTRACT_NAME: (insert_input, insert_input.FIXTURE),
    "sqlite-update-v1.json": (update, update.FIXTURE),
    update_returning.CONTRACT_NAME: (update_returning, update_returning.FIXTURE),
    "sqlite-drop-table-v1.json": (drop, drop.FIXTURE),
    "sqlite-create-table-v3.json": (create, create.FIXTURE),
    create_as.CONTRACT_NAME: (create_as, create_as.FIXTURE),
    strict_tables.CONTRACT_NAME: (strict_tables, strict_tables.FIXTURE),
    primary_keys.CONTRACT_NAME: (primary_keys, primary_keys.FIXTURE),
    rename.CONTRACT_NAME: (rename, rename.FIXTURE),
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
               rename: rename.RenameDatabase}.get(module, Database)
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
