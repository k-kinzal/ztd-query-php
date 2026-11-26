"""Compare SQLite ANALYZE results with independently predicted STAT1 contents."""

import copy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from run_sqlite_unique_keys import values_sql
from sqlite_analyze_statistics_cases import (
    SCHEMAS, analysis, case, declarations, expected, finite_id, finite_ids, forms, negatives,
    population, requests, required, schema_at,
)
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-analyze-statistics-v1.json"
CONTRACT = "732bd2ce6df5d56124baf130eb35773b13b666c9f39318802352c03056cd578a"
FIXTURE = ""
ANALYZE = 28
SCHEMA_CATALOG = frozenset((b"sqlite_master", b"sqlite_temp_master"))
STAT_COLUMNS = frozenset((b"tbl", b"idx", b"stat"))
CATALOG_COLUMNS = frozenset((b"name", b"type", b"tbl_name", b"rootpage", b"sql"))
INVALID = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"
MISSING = "requires a resolved schema, relation or index"


class StatisticsDatabase(Database):
    """Authorizes collection into native sqlite_stat1 with a closed fixture owner set."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.namespaces = frozenset()
        self.owners = frozenset()
        self.events = []

    def authorize(self, _, action, first, second, schema, origin):
        if origin is None and action == 21 and first is None and second is None and schema is None:
            return 0
        if origin is None and schema in self.namespaces:
            if action == ANALYZE and (schema, first) in self.owners and second is None:
                self.events.append([schema.decode(), first.decode()])
                return 0
            if action == 18 and first in SCHEMA_CATALOG and second is None:
                return 0
            if action in (2, 4, 9) and first == b"sqlite_stat1" and second is None:
                return 0
            if action == 23 and first in SCHEMA_CATALOG and second in CATALOG_COLUMNS:
                return 0
            if action == 20 and ((first == b"sqlite_stat1" and second in STAT_COLUMNS)
                                 or (first in SCHEMA_CATALOG and second == b"ROWID")):
                return 0
        if self.denied is None:
            self.denied = (action, [value.decode() if value is not None else None for value in (first, second, schema, origin)])
        return 1

    def collect(self, item, sql):
        self.namespaces = frozenset(schema_at(item, slot).encode() for slot in SCHEMAS)
        self.owners = frozenset((schema_at(item, slot).encode(), owner.encode()) for slot, owner, *_ in population(item))
        self.events = []
        try:
            program = self.observe("EXPLAIN " + sql, {}, preserve_order=True)["rows"]
            loaded = [int(row[2][1]) for row in program if row[1] == ["text", "LoadAnalysis"]]
            self.events = []
            result = self.observe(sql, {}, capture_evaluation_error=True)
            return result, loaded, self.events.copy()
        finally:
            self.namespaces = self.owners = frozenset()


def scoped(schema, name):
    return f"{quoted(schema)}.{quoted(name)}"


def read(db, sql):
    observation = db._observe(sql, {}, preserve_order=True)
    require(observation["readOnly"] and observation["changes"] == 0, "sqlite-statistics-observer-read", observation)
    return observation


def setup(db, item):
    require(read(db, "PRAGMA analysis_limit")["rows"] == [[["integer", "0"]]], "sqlite-statistics-analysis-limit")
    options = read(db, "PRAGMA compile_options")["rows"]
    require([["text", "ENABLE_STAT4"]] not in options, "sqlite-statistics-engine-has-stat4")
    db.execute(f"ATTACH ':memory:' AS {quoted(item['attachment'])}")
    db.execute("; ".join(declarations(item)))
    for slot, table, _, rows, _ in population(item):
        if rows:
            db.execute(f"INSERT INTO {scoped(schema_at(item, slot), table)} VALUES " + values_sql(rows))
    if item["history"] != "fresh":
        for slot in SCHEMAS:
            schema = quoted(schema_at(item, slot))
            db.execute(f"ANALYZE {schema}; UPDATE {schema}.sqlite_stat1 SET stat='99 11' WHERE tbl="
                       + "'" + item["table"].replace("'", "''") + "'")
    return options


def snapshot(db, item):
    namespaces = {}
    for slot in SCHEMAS:
        schema = quoted(schema_at(item, slot))
        table = read(db, f"SELECT name FROM {schema}.sqlite_schema WHERE type='table' AND name='sqlite_stat1'")["rows"]
        if table:
            observed = read(db, f"SELECT tbl,idx,stat FROM {schema}.sqlite_stat1 ORDER BY tbl,idx,stat")
            require(observed["names"] == ["tbl", "idx", "stat"], "sqlite-statistics-column-names", observed)
            namespaces[slot] = observed["rows"]
        else:
            namespaces[slot] = None
    return namespaces


def table_state(db, item):
    results = []
    for slot, table, heading, *_ in population(item):
        schema = schema_at(item, slot)
        order = quoted(heading[0])
        result = read(db, f"SELECT * FROM {scoped(schema, table)} ORDER BY {order}")
        results.append({"schema": schema, "name": table, "fields": result["names"], "rows": result["rows"]})
    return results


def observe(template, item, sql):
    db = type(template)(template.library)
    try:
        compile_options = setup(db, item)
        schemas = read(db, "PRAGMA database_list")["rows"]
        attachment = next(row[1][1] for row in schemas if row[0] == ["integer", "2"])
        before, tables = snapshot(db, item), table_state(db, item)
        db.execute("BEGIN")
        rounds = []
        for _ in range(2 if item["history"] == "twice" else 1):
            statement, loaded, events = db.collect(item, sql)
            rounds.append({"statement": statement, "events": sorted(events), "loaded": loaded, "statistics": snapshot(db, item)})
        active = not bool(db.get_autocommit(db.db))
        after, final_tables = snapshot(db, item), table_state(db, item)
        if active:
            db.execute("ROLLBACK")
        return {
            "before": before, "rounds": rounds, "after": after, "restored": snapshot(db, item),
            "active": active, "attachment": attachment, "tablesBefore": tables, "tablesAfter": final_tables,
            "tablesRestored": table_state(db, item), "compileOptions": compile_options,
        }
    finally:
        db.check(db.close(db.db))


def semantic_observation(result):
    return {field: value for field, value in result.items() if field != "compileOptions"}


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and reply.get("operation") == "analyze-statistics" and reply.get("target") == item["target"],
            "sqlite-statistics-bound-target", {"expected": item["target"], "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    observations = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        observations["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    prediction = expected(item)
    for result in observations.values():
        compare(prediction, semantic_observation(result))
    if ledger is not None:
        ledger.record(key, "compared")
    return observations["observation"]


def controls(db):
    item = case("without-rowid-index", "twice")
    source = "ANALYZE extension.sqlite_autoindex_no_rowid_1"
    healthy = observe(db, item, source)
    want = expected(item)
    compare(want, semantic_observation(healthy))
    clipped = copy.deepcopy(healthy)
    clipped["rounds"][1]["statistics"]["extension"].pop(0)
    different_schema = copy.deepcopy(healthy)
    different_schema["rounds"][0]["loaded"] = [0]
    faults = {}
    for label, altered in (("lost-duplicate-statistic", clipped), ("different-statistics-database", different_schema)):
        try:
            compare(want, semantic_observation(altered))
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-statistics-wrong-comparator-signal", failure.signal)
            faults[label] = {"witness": altered, "signal": failure.signal}
        else:
            raise Failure("sqlite-statistics-comparator-accepted", label)
    return {"healthy": healthy, "rejected": faults}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observation: (reply, observation))


def fault_probe(item, reply, db):
    if item.get("probe") == "missing-statistics-target":
        require(reply["status"] == "rejected" and reply["exception"] == INVALID and MISSING in reply["message"],
                "sqlite-statistics-unresolved-became-value", reply)
    else:
        probe(item, reply, db)


def check_faults(args, db, runner=check_render_faults):
    root = "sql-semantics-sqlite/src/Ir/Maintenance/"
    lower, output, lookup = root + "AnalyzeLowering.php", root + "Analyze.php", root + "MaintenanceLookup.php"
    resolve = "$lookup->index($context, $target, $scopes) ?? $lookup->relation($context, $target, $scopes)"
    known_schema, shadow = case("schema-attached"), case("unqualified-index-priority")
    return runner(args, db, [
        RenderFault("skip-analyze-schema-priority", lower, "if ($target->schema === null) {", "if (false) {",
                    known_schema, "sqlite-statistics-bound-target", analysis(known_schema, "ANALYZE extension")),
        RenderFault("choose-relation-before-statistics-index", lower, resolve,
                    "$lookup->relation($context, $target, $scopes) ?? $lookup->index($context, $target, $scopes)",
                    shadow, "sqlite-statistics-bound-target", analysis(shadow, "ANALYZE shadow")),
        RenderFault("lose-statistics-qualification", root + "RelationTarget.php", "$this->schema->quoted() . '.' . ", "",
                    case("main-table"), "meaning-mismatch"),
        RenderFault("gather-global-from-bound-statistics", output, "'ANALYZE ' . $this->target->quoted()", "'ANALYZE'",
                    case("schema-temp"), "meaning-mismatch"),
        RenderFault("gather-sibling-index-statistics", root + "IndexTarget.php", "$this->name->quoted()", "'partial'",
                    case("main-index"), "meaning-mismatch"),
        RenderFault("publish-unresolved-statistics-globally", lower,
                    "Check::input($selected !== null, 'ANALYZE requires a resolved schema, relation or index.');", "",
                    {**shadow, "probe": "missing-statistics-target"}, "sqlite-statistics-unresolved-became-value",
                    analysis(shadow, "ANALYZE main.lost")),
        RenderFault("misidentify-statistics-owner", lookup, "new Identifier($target->table->value)",
                    "new Identifier($target->name->value)", shadow, "sqlite-statistics-bound-target", analysis(shadow, "ANALYZE shadow")),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("ANALYZE-statistics", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, StatisticsDatabase)
