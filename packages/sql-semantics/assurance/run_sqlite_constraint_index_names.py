"""Compare native constraint-index lookup and guarded absence with the closed SQLite model."""

import copy
import hashlib
import re
import time
from collections import Counter

from run import Failure, Ledger, Worker, compare, require, write_json
from run_sqlite_create_table import quoted
from sqlite_add_column_cases import statement
from sqlite_constraint_index_names_cases import (
    DB_SLOTS, LAYOUTS, analysis, assemble, direct, finite_case, finite_id, finite_ids, negatives, requests,
)
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database, typed

CONTRACT_NAME = "sqlite-constraint-index-names-v2.json"
CONTRACT = "207b79eb4e08203b563f2a8b2b46553bac37baea0ffc46c2946016b63de17cd8"
FIXTURE = ""
PROTECTED = "index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped"
MODEL_PROTECTED = "cannot drop a UNIQUE or PRIMARY KEY constraint index"
INVALID = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"
HEADING = ["a", "b", "c"]


class ConstraintIndexDatabase(Database):
    """Guards a scenario where every DROP INDEX is protected or independently known absent."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def authorize(self, _, action, first, second, schema, origin):
        if self.denied is None:
            self.denied = (action, [field.decode() if field is not None else None
                                    for field in (first, second, schema, origin)])
        return 1


def query(db, sql):
    return db._observe(sql, {}, preserve_order=True)


def fixture(db, item):
    target = f"{quoted(item['schema'])}.{quoted(item['table'])}"
    db.execute("ATTACH ':memory:' AS extension")
    db.execute("CREATE TABLE temp.ir_constraint_marker (x)")
    db.execute(item["source"])
    db.execute(f"INSERT INTO {target} VALUES(1,'b','c'),(2,'d','e')")


def snapshot(db, item):
    schema = quoted(item["schema"])
    table = quoted(item["table"])
    namespace = (f"{schema}.sqlite_schema")
    columns = query(db, f"SELECT * FROM {schema}.{table} ORDER BY a")
    actual_table = query(db, f"SELECT name,sql FROM {namespace} WHERE type='table' ORDER BY name")["rows"]
    tables = [row for row in actual_table if row[0] == ["text", item["table"]]]
    require(len(tables) == 1, "sqlite-constraint-owner-mismatch", actual_table)
    slots = query(db, "PRAGMA database_list")["rows"]
    scope = next(row[1][1] for row in slots if int(row[0][1]) == DB_SLOTS[item["schema"]])
    declaration = tables[0][1][1]
    definition = declaration.split("(", 1)[1]
    matches = [layout for layout, (content, _) in LAYOUTS.items()
               if definition == content + ("" if content.endswith(("WITHOUT ROWID", "STRICT")) else ")")]
    layout = matches[0] if matches else "generated"
    return {
        "declared": {"schema": scope, "table": tables[0][0][1], "layout": layout, "definition": declaration},
        "listed": query(db, f"PRAGMA {schema}.index_list({table})")["rows"],
        "catalog": query(db, f"SELECT name,tbl_name FROM {namespace} WHERE type='index' ORDER BY name")["rows"],
        "rows": columns["rows"], "heading": columns["names"],
    }


def check_fixture(item, observation):
    listed = observation["listed"]
    names = {f"sqlite_autoindex_{item['table']}_{n}" for n in range(1, item["count"] + 1)}
    require(len(listed) == len(names) and {row[1][1] for row in listed} == names, "sqlite-constraint-fixture-index-names", observation)
    if item["withoutRowid"]:
        primary = [row[1][1] for row in listed if row[3] == ["text", "pk"]]
        require(len(primary) == 1, "sqlite-constraint-fixture-primary", listed)
        names.remove(primary[0])
    compare([[typed(name), typed(item["table"])] for name in sorted(names)], observation["catalog"])
    numeric = bool(re.match(r"a (INTEGER|INT)\b", item["source"].split("(", 1)[1]))
    compare(HEADING, observation["heading"])
    compare([[typed(1 if numeric else "1"), typed("b"), typed("c")],
             [typed(2 if numeric else "2"), typed("d"), typed("e")]], observation["rows"])


def observe(template, item, sql):
    db = type(template)(template.library)
    try:
        fixture(db, item)
        before = snapshot(db, item)
        check_fixture(item, before)
        try:
            plan = db.observe("EXPLAIN " + sql, {}, preserve_order=True)
            operation = db.observe(sql, {}, capture_evaluation_error=True)
            lookup = [int(row[2][1]) for row in plan["rows"] if row[1] == ["text", "Transaction"]]
        except RuntimeError as error:
            if str(error) != "SQLite 1: " + PROTECTED:
                raise
            operation, lookup = {"prepareError": str(error)}, []
        return {"before": before, "operation": operation, "verification": lookup, "after": snapshot(db, item)}
    finally:
        db.check(db.close(db.db))


def native_expected(item, native):
    compare(native["before"], native["after"])
    if item["protected"]:
        compare({"prepareError": "SQLite 1: " + PROTECTED}, native["operation"])
        compare([], native["verification"])
    else:
        compare(statement(), native["operation"])
        compare([0, 1, 2] if item["qualifier"] is None else [DB_SLOTS[item["schema"]]], native["verification"])


def assert_rejection(reply, text):
    require(reply["status"] == "rejected" and reply["exception"] == INVALID and text in reply["message"],
            "sqlite-constraint-index-refusal", reply)


def probe(item, reply, db, ledger=None, key=None, original=None):
    if item["protected"]:
        assert_rejection(reply, MODEL_PROTECTED)
        native = observe(db, item, item["dropped"])
        native_expected(item, native)
        require(item["selector"].lower() in {row[1][1].lower() for row in native["before"]["listed"]},
                "sqlite-constraint-native-rejection-coordinate", native)
        if ledger is not None:
            ledger.record(key, "rejected", response=reply, native=native)
        return native

    expected = direct(item)
    require(reply["status"] == "constructed" and all(reply.get(field) == value for field, value in expected.items()),
            "sqlite-constraint-expected-absent", {"expected": expected, "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    native = observe(db, item, reply["sql"])
    outcomes = {"observation": native}
    if original is not None:
        outcomes["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **outcomes)
    for observed in outcomes.values():
        native_expected(item, observed)
    if ledger is not None:
        ledger.record(key, "compared")
    return native


def controls(db):
    protected = finite_case("temp", "rows", "shared-without-rowid", 1)
    healthy = observe(db, protected, protected["dropped"])
    native_expected(protected, healthy)
    counterfeit = copy.deepcopy(healthy)
    counterfeit["operation"] = statement()
    try:
        native_expected(protected, counterfeit)
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "sqlite-constraint-invalid-comparator-signal", failure.signal)
        return {"healthy": healthy, "knownWrong": counterfeit, "signal": failure.signal}
    raise Failure("sqlite-constraint-comparator-accepted")


def execute(args, specification, db, worker_factory=Worker):
    domain = specification["finite"]
    metrics = specification["exploration"]
    seen, outcomes = Counter(), {}
    unqualified = 0
    negative_count = 0
    check = controls(db)
    current = key = None
    with (args.out / "events.jsonl").open("w") as output, worker_factory(args, time.monotonic() + metrics["timeoutSeconds"]) as worker:
        ledger = Ledger(output)
        try:
            for key, item in requests(specification):
                current = analysis(item)
                phase = key.split("/", 1)[0]
                ledger.record(key, "attempted", input=current, expected=item)
                reply = worker.request(current)
                observed = probe(item, reply, db, ledger, key, item["dropped"])
                outcome = "protected" if reply["status"] == "rejected" else "absent"
                outcomes.setdefault(phase, Counter())[outcome] += 1
                if phase == "finite":
                    measured = {**item, **({"selector": reply["name"]} if outcome == "absent" else {})}
                    seen[finite_id(measured, observed["before"], domain)] += 1
                if phase == "seed" and item["qualifier"] is None:
                    unqualified += 1
                if outcome == "absent":
                    key += "/direct"
                    current = direct(item)
                    ledger.record(key, "attempted", input=current, expected=item)
                    direct_observation = probe(item, worker.request(current), db, ledger, key)
                    compare(observed, direct_observation)

            for number, (current, reason, native) in enumerate(negatives()):
                key = f"negative/{number}"
                ledger.record(key, "attempted", input=current)
                response = worker.request(current)
                assert_rejection(response, reason)
                observation = None if native is None else observe(db, native, native["dropped"])
                if observation is not None:
                    native_expected(native, observation)
                ledger.record(key, "rejected", response=response, native=observation)
                negative_count += 1

            spec = specification["schedule"]
            expected = {"finite": spec["finiteOutcomes"], "seed": spec["explorationOutcomes"], "authored": spec["authoredOutcomes"]}
            require(seen.keys() == finite_ids(domain) and set(seen.values()) == {1}, "sqlite-constraint-finite-incomplete", sorted(seen))
            compare(expected, outcomes)
            require(unqualified == metrics["minimumObserved"]["unqualified"] and negative_count == len(specification["negative"]),
                    "sqlite-constraint-required-coverage", [unqualified, negative_count])
            for record, events in ledger.events.items():
                require(events in (["attempted", "constructed", "observed", "compared"], ["attempted", "rejected"]),
                        "unbalanced-constraint-execution", [record, events])
        except Exception as error:
            signal = error.signal if isinstance(error, Failure) else "unexpected-error"
            detail = error.detail if isinstance(error, Failure) else None
            write_json(args.out / "counterexample.json",
                       {"id": key, "original": current, "signal": signal, "detail": detail, "shrunk": None})
            raise
    return {"counts": dict(ledger.counts), "finiteIds": sorted(seen), "outcomes": outcomes,
            "unqualified": unqualified, "php": worker.environment, "controls": check}


def fault_probe(item, reply, db):
    if item.get("probe") == "opaque-namespace":
        assert_rejection(reply, "complete implicit index namespace")
    else:
        probe(item, reply, db)


def check_faults(args, db, runner=check_render_faults):
    catalog = "sql-semantics-sqlite/src/Rules/Definition/Index/ConstraintIndexes.php"
    lower = "sql-semantics-sqlite/src/Ir/Schema/Index/DropIndexLowering.php"

    def source(name, path, before, replacement, item, signal):
        return RenderFault(name, path, before, replacement, item, signal, analysis(item))

    absent_alias = finite_case("main", "test", "rowid-alias", 1)
    no_rowid = assemble("main", "test", "separate-without-rowid-key", 2,
                        "a INTEGER,b TEXT UNIQUE,c TEXT,PRIMARY KEY(a)) WITHOUT ROWID", 2)
    equivalent = finite_case("temp", "test", "same-key", 2)
    distinct = finite_case("temp", "test", "distinct-collations", 2)
    protected = finite_case("extension", "test", "inline-desc", 1)
    incomplete = analysis(absent_alias, declarations=[absent_alias["source"], "CREATE VIRTUAL TABLE main.opaque USING fts5(a)"])
    return runner(args, db, [
        source("count-rowid-alias-index", catalog, "$alias = $table->withoutRowid() ? null : $primary->rowid;",
               "$alias = null;", absent_alias, "sqlite-constraint-expected-absent"),
        source("skip-without-rowid-key", catalog, "$table->withoutRowid() ? null : $primary->rowid",
               "$primary->rowid", no_rowid, "sqlite-constraint-index-refusal"),
        source("double-count-equivalent-keys", catalog, "$keys[$this->signature($terms)] = true;",
               "$keys[] = true;", equivalent, "sqlite-constraint-expected-absent"),
        source("discard-key-collation", catalog,
               "$expression instanceof Collate ? $names->fold($expression->collation->value) : $collations[$slot]",
               "$collations[$slot]", distinct, "sqlite-constraint-index-refusal"),
        source("publish-protected-as-absent", lower,
               "Check::input($index->removable, 'SQLite cannot drop a UNIQUE or PRIMARY KEY constraint index.');",
               "if (!$index->removable) { return new DropIndex(new MissingIndex($name, $schema === null ? null : new Identifier($schema->value)), true); }",
               protected, "sqlite-constraint-index-refusal"),
        RenderFault("ignore-opaque-index-namespace", lower, "$context->knowsConstraintIndexes($schema)", "true",
                    {**absent_alias, "probe": "opaque-namespace"}, "sqlite-constraint-index-refusal", incomplete),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("constraint-index-names", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, ConstraintIndexDatabase)
