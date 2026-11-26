"""Compare SQLite REINDEX targets using native authorization and physical index order."""

import copy
import ctypes as c
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from run_sqlite_unique_keys import values_sql
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database
from sqlite_reindex_cases import (
    analysis, case, declaration, expected, finite_id, finite_ids, forms, names, negatives, population,
    requests, required,
)
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-reindex-v2.json"
CONTRACT = "a8f6e5b5455eefec2b7940c123aeddaf080afb76be02467465171866221178fc"
FIXTURE = ""
REINDEX = 27
COLLATION = c.CFUNCTYPE(c.c_int, c.c_void_p, c.c_int, c.c_void_p, c.c_int, c.c_void_p)
ASCII_FOLD = bytes.maketrans(b"ABCDEFGHIJKLMNOPQRSTUVWXYZ", b"abcdefghijklmnopqrstuvwxyz")
INVALID = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"
MISSING = "requires a resolved collation, relation or index"


class ReindexDatabase(Database):
    """Allows only rebuilding explicitly installed fixture indexes."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.coordinates = frozenset()
        self.events = []
        self.collations = {}
        self.register = self.lib.sqlite3_create_collation
        self.register.argtypes = [c.c_void_p, c.c_char_p, c.c_int, c.c_void_p, COLLATION]
        self.register.restype = c.c_int

    def authorize(self, _, action, first, second, schema, origin):
        if action == REINDEX and (schema, first) in self.coordinates and second is None and origin is None:
            self.events.append([schema.decode(), first.decode()])
            return 0
        if self.denied is None:
            self.denied = (action, [part.decode() if part is not None else None for part in (first, second, schema, origin)])
        return 1

    def collation(self, name, reverse, sensitive=False):
        def key(pointer, length):
            value = c.string_at(pointer, length)
            if name == "RTRIM":
                return value.rstrip(b" ")
            return value if sensitive else value.translate(ASCII_FOLD)

        def order(_, left_size, left_data, right_size, right_data):
            left, right = key(left_data, left_size), key(right_data, right_size)
            result = (left > right) - (left < right)
            return -result if reverse else result

        comparator = COLLATION(order)
        self.check(self.register(self.db, name.encode(), 1, None, comparator))
        self.collations[name] = comparator

    def rebuild(self, sql, indexes):
        self.coordinates = frozenset((index.schema.encode(), index.name.encode()) for index in indexes)
        self.events = []
        try:
            return self.observe(sql, {}, capture_evaluation_error=True), self.events.copy()
        finally:
            self.coordinates = frozenset()


def scoped(schema, name):
    return f"{quoted(schema)}.{quoted(name)}"


def read(db, sql):
    observed = db._observe(sql, {}, preserve_order=True)
    require(observed["readOnly"] and observed["changes"] == 0, "sqlite-reindex-observer-read", observed)
    return observed


def collation_orders(db, item):
    strings = sorted({value for _, _, _, rows in population(item) for row in rows for value in row if isinstance(value, str)})
    data = values_sql((position, value) for position, value in enumerate(strings))
    prefix = f"WITH fixture(position, value) AS (VALUES {data})"
    results = {}
    for name in ("NOCASE", "RTRIM"):
        sql = f"{prefix} SELECT value, DENSE_RANK() OVER (ORDER BY value COLLATE {name}) AS level " \
              f"FROM fixture ORDER BY value COLLATE {name}, position"
        results[name] = read(db, sql)["rows"]
    return results


def setup(db, item):
    stock = collation_orders(db, item)
    db.execute(f"ATTACH ':memory:' AS {quoted(item['attached'])}")
    unique_conflict = item["storage"] == "unique-conflict"
    db.collation("NOCASE", item["storage"] != "consistent", unique_conflict)
    db.collation("RTRIM", item["storage"] in ("both-stale", "unique-conflict"))
    db.execute("; ".join(declaration(item)))
    for schema, table, _, rows in population(item):
        db.execute(f"INSERT INTO {scoped(schema, table)} VALUES " + values_sql(rows))
    db.collation("NOCASE", False)
    db.collation("RTRIM", False)
    active = collation_orders(db, item)
    compare(stock, active)

    return {"stock": stock, "active": active}


def checked_scans(db, indexes):
    result = []
    for index in indexes:
        column = quoted(index.column)
        sql = f"SELECT {column} FROM {scoped(index.schema, index.owner)} INDEXED BY {quoted(index.name)} ORDER BY {column} COLLATE {index.collation}"
        plan = read(db, "EXPLAIN QUERY PLAN " + sql)["rows"]
        require(len(plan) == 1 and plan[0][3][1].startswith("SCAN "), "sqlite-reindex-unobserved-index-order", plan)
        if index.owner == "hidden":
            require(" USING " not in plan[0][3][1] and index.name.startswith("sqlite_autoindex_"),
                    "sqlite-reindex-unobserved-primary-storage", plan)
        else:
            require(plan[0][3][1].endswith(" INDEX " + index.name), "sqlite-reindex-wrong-forced-index", plan)
        contents = read(db, sql)
        result.append({"schema": index.schema, "index": index.name, "owner": index.owner,
                       "names": contents["names"], "rows": contents["rows"], "plan": plan, "sql": sql})
    return result


def tables(db, item):
    observed = []
    for schema, owner, fields, _ in population(item):
        order = "id" if "id" in fields else "rowid"
        result = read(db, f"SELECT * FROM {scoped(schema, owner)} ORDER BY {order}")
        observed.append({"schema": schema, "name": owner, "names": result["names"], "rows": result["rows"]})
    return observed


def observe(template, item, sql):
    db = type(template)(template.library)
    try:
        profile = setup(db, item)
        indexes = list(names(item))
        attached_rows = read(db, "PRAGMA database_list")["rows"]
        attached = next(row[1][1] for row in attached_rows if row[0] == ["integer", "2"])
        keys = read(db, f"PRAGMA main.index_list({quoted(item['table'])})")["rows"]
        main_unique = next(row[2][1] == "1" for row in keys if row[1] == ["text", "rev_main"])
        before, early = checked_scans(db, indexes), tables(db, item)
        db.execute("BEGIN")
        statement, events = db.rebuild(sql, indexes)
        refreshed, current = checked_scans(db, indexes), tables(db, item)
        active = not bool(db.get_autocommit(db.db))
        if active:
            db.execute("ROLLBACK")
        return {
            "statement": statement, "events": sorted(events), "rawAuthorizationOrder": events, "profileCalibration": profile,
            "attached": attached, "before": before, "after": refreshed, "initialTables": early, "finalTables": current,
            "rolledBack": checked_scans(db, indexes), "restoredTables": tables(db, item), "mainUnique": main_unique, "active": active,
        }
    finally:
        db.check(db.close(db.db))


def semantic_observation(result):
    selected = {name: value for name, value in result.items() if name not in ("rawAuthorizationOrder", "profileCalibration")}
    for key in ("before", "after", "rolledBack"):
        selected[key] = [{field: value for field, value in occurrence.items() if field not in ("plan", "sql")}
                         for occurrence in result[key]]
    return selected


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and reply.get("operation") == "reindex" and reply.get("target") == item["target"],
            "sqlite-reindex-bound-target", {"expected": item["target"], "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    results = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        results["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **results)
    desired = expected(item)
    for result in results.values():
        compare(desired, semantic_observation(result))
    if ledger is not None:
        ledger.record(key, "compared")
    return results["observation"]


def controls(db):
    item = case("main-table")
    actual = observe(db, item, "REINDEX main.records")
    want = expected(item)
    compare(want, semantic_observation(actual))
    mutated = copy.deepcopy(actual)
    mutated["after"][0]["rows"].reverse()
    wrong_target = copy.deepcopy(actual)
    wrong_target["events"] = wrong_target["events"][:-1]
    rejected = {}
    for role, altered in (("physical-order", mutated), ("rebuild-identity", wrong_target)):
        try:
            compare(want, semantic_observation(altered))
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-reindex-wrong-comparator-signal", failure.signal)
            rejected[role] = {"witness": altered, "signal": failure.signal}
        else:
            raise Failure("sqlite-reindex-comparator-accepted", role)
    return {"healthy": actual, "rejected": rejected}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observation: (reply, observation))


def fault_probe(item, reply, db):
    if item.get("probe") == "reject-unknown":
        require(reply["status"] == "rejected" and reply["exception"] == INVALID and MISSING in reply["message"],
                "sqlite-reindex-unknown-became-value", reply)
    else:
        probe(item, reply, db)


def check_faults(args, db, runner=check_render_faults):
    root = "sql-semantics-sqlite/src/Ir/Maintenance/"
    lowering, rebuild = root + "ReindexLowering.php", root + "Reindex.php"
    named = case("unqualified-table-priority")
    temp = case("temp-shadow-index")
    nocase = case("nocase")
    resolve = "$lookup->relation($context, $target, $scopes) ?? $lookup->index($context, $target, $scopes)"
    missing = "Check::input($selected !== null, 'REINDEX requires a resolved collation, relation or index.');"
    return runner(args, db, [
        RenderFault("ignore-builtin-before-relation", lowering, "if ($target->schema === null) {", "if (false) {",
                    nocase, "sqlite-reindex-bound-target", analysis(nocase, "REINDEX NOCASE")),
        RenderFault("rebuild-index-before-resolving-relation", lowering, resolve,
                    "$lookup->index($context, $target, $scopes)",
                    named, "sqlite-reindex-bound-target", analysis(named, "REINDEX shadow")),
        RenderFault("lose-reindex-schema", root + "RelationTarget.php", "$this->schema->quoted() . '.' . ", "",
                    case("main-table"), "meaning-mismatch"),
        RenderFault("rebuild-all-from-named-target", rebuild, "'REINDEX ' . $this->target->quoted()", "'REINDEX'",
                    case("main-table"), "meaning-mismatch"),
        RenderFault("rebuild-the-sibling-index", root + "IndexTarget.php", "$this->name->quoted()", "'rev_temp'",
                    temp, "meaning-mismatch"),
        RenderFault("reindex-unresolved-as-all", lowering, missing, "",
                    {**named, "probe": "reject-unknown"}, "sqlite-reindex-unknown-became-value", analysis(named, "REINDEX unknown")),
        RenderFault("lose-resolved-index-owner", root + "MaintenanceLookup.php", "new Identifier($target->table->value)",
                    "new Identifier($target->name->value)", temp, "sqlite-reindex-bound-target", analysis(temp, "REINDEX temp.shadow")),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("REINDEX", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, ReindexDatabase)
