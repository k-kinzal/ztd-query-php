"""Reproduce SQLite evidence using recorded PHP responses and a fresh database."""

import json
import tempfile
from pathlib import Path
from types import SimpleNamespace

import run
import run_sqlite_create_table as create
import run_sqlite_delete as delete
import run_sqlite_drop_table as drop
import run_sqlite_insert as insert
import run_sqlite_local_source as local
import run_sqlite_relational as relational
import run_sqlite_result as result
import run_sqlite_scalars as scalars
import run_sqlite_update as update
import run_sqlite_values as values
from cases import SCHEMA
from evidence_transcript import Transcript, canonical, require_reproduced
from run import TOOL, require
from sqlite_engine import Database

DOMAINS = {
    "sqlite-relational-v1.json": (relational, relational.SCHEMA),
    "sqlite-result-v1.json": (result, result.SCHEMA + result.PAGE_DDL + ";" + result.PAGE_ROWS),
    "sqlite-delete-v1.json": (delete, delete.SCHEMA),
    "sqlite-values-v1.json": (values, ""),
    "sqlite-local-source-v1.json": (local, local.DATA),
    "sqlite-insert-v1.json": (insert, insert.FIXTURE),
    "sqlite-update-v1.json": (update, update.FIXTURE),
    "sqlite-drop-table-v1.json": (drop, drop.FIXTURE),
    "sqlite-create-table-v1.json": (create, create.FIXTURE),
    "sqlite-scalars-v1.json": (scalars, scalars.FIXTURE),
}


def replay(directory, contract_name, index, library):
    contract = json.loads((TOOL / contract_name).read_text())
    path = directory / "events.jsonl"
    checks = index["checks"]
    initial = contract_name == "contract-v1.json"
    transcript = Transcript(path, checks.get("php", checks.get("candidateEnvironment")))

    def worker_factory(*_):
        return transcript

    database = Database(library)
    try:
        require(database.version == "3.47.2" and database.source == index["engine"]["source"]
                and database.digest == index["engine"]["libraryDigest"], "evidence-replay-engine-mismatch", contract_name)
        module, fixture = (None, SCHEMA) if initial else DOMAINS[contract_name]
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
