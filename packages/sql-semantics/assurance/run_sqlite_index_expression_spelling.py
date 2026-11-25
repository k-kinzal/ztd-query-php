"""Observe native planner matching and typed index values across SQLite CAST and IFNULL spellings."""

import copy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_create_index import FIXTURE, IndexDatabase, check_binding_contract, check_binding_projection, later, observe
from run_sqlite_create_table import quoted
from run_sqlite_scalars import evaluate, order_value
from sqlite_cast_semantics import convert
from sqlite_create_index_cases import FIELDS, declarations, expected as expected_index, input_rows, writes
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import typed
from sqlite_index_expression_spelling_cases import (
    ROLES, authored, conversion, finite_id, finite_ids, forms, native_sql, negatives, parts,
    requests, required, value,
)
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-index-expression-spelling-v2.json"
CONTRACT = "134cb0f660c983504545ad053bb4cfecc934cc8cf56d3e91492af20bd6a47a29"
NO_PLAN = "SQLite 1: no query solution"
SQLITE_FUNCTION = 31
SLOT = '"amount"'


class SpellingIndexDatabase(IndexDatabase):
    """Uses the fixed SQLite index authorizer with the native spelling contract."""

    policy_digest = hashlib.sha256(f"{IndexDatabase.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def authorize(self, context, action, first, second, schema, origin):
        if self.authorized and action == SQLITE_FUNCTION and first is None and schema is None and origin is None \
                and second in (b"ifnull", b"coalesce"):
            return 0
        return super().authorize(context, action, first, second, schema, origin)


def differences(scalar):
    wrapped = scalar[0] != "cast"
    if wrapped:
        switched = "coalesce" if scalar[0] == "if-null" else "if-null"
        yield "builtin", [switched, scalar[1]]
    node = scalar[1][0] if wrapped else scalar
    if node[0] == "cast":
        name = node[3] if len(node) == 4 else node[1]
        alternate = node[1] + " " if name == node[1] else node[1]
        assert alternate != name and conversion(alternate) == node[1]
        replacement = [*node[:3], alternate]
        yield "type", [scalar[0], [replacement, scalar[1][1]]] if wrapped else replacement


def target(item):
    return f"{item['schema']}.{quoted(item['table'])} INDEXED BY {quoted(item['index'])}"


def needle(scalar):
    node = scalar[1][0] if scalar[0] != "cast" else scalar
    return convert(node[1], 3)


def search_query(item, scalar, bound):
    expression = native_sql(scalar, SLOT)
    if isinstance(bound, bytes):
        constant = "X'" + bound.hex() + "'"
    elif isinstance(bound, str):
        constant = "'" + bound.replace("'", "''") + "'"
    else:
        constant = repr(bound)
    return f'SELECT "id" AS "slot", {expression} AS "value" FROM {target(item)} WHERE {expression} = {constant} ORDER BY "id"'


def check_plan(db, query, index):
    plan = db.read("EXPLAIN QUERY PLAN " + query)["rows"]
    require(bool(plan) and (len(plan) == 1 or (len(plan) == 2 and plan[1][3] == ["text", "USE TEMP B-TREE FOR ORDER BY"])),
            "sqlite-spelling-plan-shape", plan)
    detail = plan[0][3][1]
    search = detail.startswith("SEARCH ") and f"USING INDEX {index} (<expr>=?)" in detail
    scan = detail.startswith("SCAN ") and detail.endswith(f"USING INDEX {index}")
    require(search or scan, "sqlite-spelling-unknown-plan", plan)
    return search, plan


def keys(db, item, scalar):
    probes = {}
    bound = needle(scalar)
    for role, expression in [("same", scalar), *differences(scalar)]:
        sql = search_query(item, expression, bound)
        search, plan = check_plan(db, sql, item["index"])
        result = db.read(sql)
        probes[role] = {"search": search, "names": result["names"], "rows": result["rows"], "plan": plan, "sql": sql}
    return probes


def future(db, item):
    placement, scalar = parts(item)
    result = later(db, item, lambda fixture: "" if fixture["where"] is None else " WHERE " + native_sql(fixture["where"], SLOT))
    if placement == "key":
        result["planner"] = keys(db, item, scalar)
        return result
    placements = {}
    for role, expression in differences(scalar):
        changed = [*item["where"][:2], expression, item["where"][3]]
        sql = f'SELECT "id" FROM {target(item)} WHERE {native_sql(changed, SLOT)} ORDER BY "id"'
        try:
            placements[role] = {"sql": sql, "rows": db.read(sql)["rows"]}
        except RuntimeError as error:
            if str(error) != NO_PLAN:
                raise
            placements[role] = {"sql": sql, "prepareError": str(error)}
    result["planner"] = placements
    return result


def prediction(item):
    result = expected_index(item)
    placement, scalar = parts(item)
    if placement == "partial":
        result["later"]["planner"] = {role: {"prepareError": NO_PLAN} for role, _ in differences(scalar)}
        return result

    bound_value = needle(scalar)
    rows = []
    for row in [*input_rows(item, item["schema"]), *writes(item)]:
        key = evaluate(scalar, {}, row)
        if key is not None and order_value(key) == order_value(bound_value):
            rows.append([typed(row[0]), typed(key)])
    result["later"]["planner"] = {
        role: {"search": role == "same", "names": ["slot", "value"], "rows": rows}
        for role, _ in [("same", scalar), *differences(scalar)]
    }
    return result


def compare_observations(expected, actual):
    observation = {**actual, "later": {**actual["later"]}}
    planner = observation["later"]["planner"]
    observation["later"]["planner"] = {
        role: {field: value for field, value in metrics.items() if field not in ("plan", "sql")}
        for role, metrics in planner.items()
    }
    require(set(planner) == set(expected["later"]["planner"]), "sqlite-spelling-probe-roles", planner)
    compare(expected, observation)


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and all(reply.get(field) == item[field] for field in FIELDS),
            "sqlite-index-spelling-structure", {"expected": item, "actual": reply})
    check_binding_projection(item, reply)
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    observations = {"observation": observe(db, item, reply["sql"], future)}
    if original is not None:
        observations["original"] = observe(db, item, original, future)
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    expected = prediction(item)
    for observation in observations.values():
        compare_observations(expected, observation)
    if ledger is not None:
        ledger.record(key, "compared")
    return observations["observation"]


def controls(db):
    fixtures = (
        ("key", value("main", "key", ROLES[0], "INT", "mixed"), "CREATE INDEX main.tag ON ledger(CAST(amount AS INT))"),
        ("partial", value("temp", "partial", ROLES[1], "int", "mixed"),
         "CREATE INDEX temp.tag ON ledger(amount) WHERE IFNULL(CAST(amount AS int),0) IS NOT NULL"),
    )
    results = {}
    for placement, item, sql in fixtures:
        expected = prediction(item)
        healthy = observe(db, item, sql, future)
        compare_observations(expected, healthy)
        changed = copy.deepcopy(healthy)
        planner = changed["later"]["planner"]
        if placement == "key":
            planner["type"]["search"] = True
        else:
            planner["builtin"] = {"sql": planner["builtin"]["sql"], "rows": []}
        try:
            compare_observations(expected, changed)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-index-spelling-invalid-control", error.signal)
            results[placement] = {"normal": healthy, "witness": changed, "observed": error.signal}
        else:
            raise Failure("sqlite-index-spelling-comparator-accepted", changed)
    return results


def execute(args, specification, db, worker_factory=Worker):
    check_binding_contract(specification)
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, result: (reply, result))


def check_faults(args, db, runner=check_render_faults):
    base = "sql-semantics-sqlite/src/"
    select = base + "Ir/SelectSql.php"
    quoted_name = base + "Ir/Expression/CastTypeName.php"
    layout = base + "Statement/Expression/Cast.php"
    comment_case, source_sql = next((item, sql) for item, sql in authored() if "/* INT */" in sql)
    return runner(args, db, [
        RenderFault("normalize-index-cast-name", select, "$expression->typeName?->quoted() ?? $expression->target->value",
                    "$expression->target->value", value("main", "partial", ROLES[0], "INT", "mixed"), "meaning-mismatch"),
        RenderFault("case-change-index-cast-name", quoted_name, """str_replace('"', '""', $this->value)""",
                    """str_replace('"', '""', strtoupper($this->value))""",
                    value("temp", "key", ROLES[0], "int", "mixed"), "meaning-mismatch"),
        RenderFault("lose-embedded-index-type-quotes", quoted_name, """str_replace('"', '""', $this->value)""",
                    """str_replace('"', '', $this->value)""",
                    value("temp", "key", ROLES[0], 'Name "with" INT', "mixed"), "meaning-mismatch"),
        RenderFault("change-index-ifnull-builtin", select, "$expression->ifNull ? 'IFNULL(' : 'COALESCE('",
                    "'COALESCE('", value("temp", "partial", ROLES[1], "INT", "mixed"), "meaning-mismatch"),
        RenderFault("omit-index-cast-operand", select, "'(CAST(' . $this->expression($expression->operand, $source, $joins)",
                    "'(CAST(' . '0'", value("temp", "key", ROLES[0], "INT", "mixed"), "meaning-mismatch"),
        RenderFault("ignore-index-cast-token-layout", layout, "$this->typeLayout !== null", "false", comment_case, "sqlite-index-spelling-structure",
                    {"sql": source_sql, "declarations": declarations(comment_case)}),
    ], probe)


if __name__ == "__main__":
    run_domain("index-expression-spelling", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, SpellingIndexDatabase)
