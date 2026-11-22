"""Reproduce SQLite evidence using recorded PHP responses and a fresh database."""

import json
import tempfile
from pathlib import Path
from types import SimpleNamespace

import run
import run_sqlite_case as choice
import run_sqlite_compound as compound
import run_sqlite_create_table as create
import run_sqlite_delete as delete
import run_sqlite_defaults as defaults
import run_sqlite_drop_table as drop
import run_sqlite_grouped as grouped
import run_sqlite_insert as insert
import run_sqlite_insert_query as insert_query
import run_sqlite_local_source as local
import run_sqlite_recursive_source as recursive
import run_sqlite_relational as relational
import run_sqlite_result as result
import run_sqlite_scalars as scalars
import run_sqlite_transaction as transaction
import run_sqlite_update as update
import run_sqlite_values as values
import run_sqlite_wildcard as wildcard
from cases import SCHEMA
from evidence_transcript import Transcript, canonical, require_reproduced
from run import TOOL, require
from sqlite_engine import Database

DOMAINS = {
    choice.CONTRACT_NAME: (choice, choice.FIXTURE),
    defaults.CONTRACT_NAME: (defaults, defaults.FIXTURE),
    "sqlite-relational-v1.json": (relational, relational.SCHEMA),
    "sqlite-result-v1.json": (result, result.SCHEMA + result.PAGE_DDL + ";" + result.PAGE_ROWS),
    "sqlite-delete-v1.json": (delete, delete.SCHEMA),
    "sqlite-values-v1.json": (values, ""),
    "sqlite-local-source-v1.json": (local, local.DATA),
    recursive.CONTRACT_NAME: (recursive, recursive.FIXTURE),
    "sqlite-insert-v1.json": (insert, insert.FIXTURE),
    insert_query.CONTRACT_NAME: (insert_query, insert_query.FIXTURE),
    "sqlite-update-v1.json": (update, update.FIXTURE),
    "sqlite-drop-table-v1.json": (drop, drop.FIXTURE),
    "sqlite-create-table-v1.json": (create, create.FIXTURE),
    "sqlite-scalars-v1.json": (scalars, scalars.FIXTURE),
    "sqlite-compound-v1.json": (compound, compound.FIXTURE),
    grouped.CONTRACT_NAME: (grouped, grouped.FIXTURE),
    wildcard.CONTRACT_NAME: (wildcard, wildcard.SCHEMA),
    "sqlite-transaction-v1.json": (transaction, transaction.FIXTURE),
}


def replay(directory, contract_name, index, library):
    contract = json.loads((TOOL / contract_name).read_text())
    path = directory / "events.jsonl"
    checks = index["checks"]
    initial = contract_name == "contract-v1.json"
    transcript = Transcript(path, checks.get("php", checks.get("candidateEnvironment")))

    def worker_factory(*_):
        return transcript

    module, fixture = (None, SCHEMA) if initial else DOMAINS[contract_name]
    factory = {recursive: recursive.RecursiveDatabase, grouped: grouped.GroupedDatabase}.get(module, Database)
    database = factory(library)
    try:
        require(database.version == "3.47.2" and database.source == index["engine"]["source"]
                and database.digest == index["engine"]["libraryDigest"], "evidence-replay-engine-mismatch", contract_name)
        require(index["engine"].get("observerPolicy") == database.policy_digest, "evidence-observer-policy-mismatch", contract_name)
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
        return transcript.consumed
    finally:
        database.close(database.db)
