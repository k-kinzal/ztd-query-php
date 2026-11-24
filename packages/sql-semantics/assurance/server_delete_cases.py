"""Direct construction and independent post-state rules for native DELETE."""

import itertools
import json
import random

from mysql_physical_text_admission import negative as mysql_text_negative
from run import require
from postgres_physical_text_admission import negative as postgres_text_negative
from server_cases import SCHEMAS
from server_truth_model import COMPARISONS as OPERATORS, admits

ROWS = {
    "target": [[-2, 1], [-1, -1], [0, 2], [0, 2], [None, -1], [1, None], [2, 0], [2147483647, -2147483648]],
    "child": [[-1, 5], [0, 2], [None, 3], [3, 3]],
    "control": [[99, 2], [9, 99], [0, None]],
    "events": [],
}


def literal(value):
    return ["integer", str(value)]


ATOMS = (["slot", 0], ["slot", 1], literal(0))
CONSTANTS = (None, ["boolean", True], ["boolean", False], ["null"])
COMPOSED = (
    ["logic", "NOT", ["null"], None],
    ["null-test", False, ["slot", 0]],
    ["logic", "AND", ["null-test", False, ["slot", 1]], ["comparison", "<", ["slot", 0], literal(2)]],
)


def target(dialect, only=False, columns=("id", "delta"), table="target"):
    return {
        "schema": SCHEMAS[dialect], "table": "ir_delete_" + table, "columns": list(columns),
        **({"only": only} if dialect == "postgres" else {}),
    }


def case(dialect, tag, where, only=False, columns=("id", "delta"), table="target"):
    return {"dialect": dialect, "release": tag, "target": target(dialect, only, columns, table), "where": where}


def finite_cases(dialect, tag):
    policies = (False, True) if dialect == "postgres" else (False,)
    for only in policies:
        for predicate in CONSTANTS:
            yield case(dialect, tag, predicate, only)
        for predicate in COMPOSED:
            yield case(dialect, tag, predicate, only)
        for comparator, left, right in itertools.product(OPERATORS, ATOMS, ATOMS):
            yield case(dialect, tag, ["comparison", comparator, left, right], only)


def finite_id(response):
    predicate = response["where"]
    policy = response["target"].get("only", False)
    if predicate in CONSTANTS:
        return policy, CONSTANTS.index(predicate)
    if predicate in COMPOSED:
        return policy, 58 + COMPOSED.index(predicate)
    _, comparator, left, right = predicate
    return policy, 4 + list(OPERATORS).index(comparator) * 9 + ATOMS.index(left) * 3 + ATOMS.index(right)


def finite_ids(dialect, specification):
    return {(policy == "ONLY", index)
            for policy in specification["finite"]["policies"][dialect]
            for index in range(specification["finite"]["identitiesPerPolicy"])}


def seeded(dialect, tag, seed, count):
    rng = random.Random(seed)
    for _ in range(count):
        atoms = [*ATOMS, literal(rng.randrange(-(2 ** 31), 2 ** 31)), literal(rng.choice((-2147483648, 2147483647)))]
        comparison = ["comparison", rng.choice(tuple(OPERATORS)), rng.choice(atoms), rng.choice(atoms)]
        where = rng.choice((*CONSTANTS, comparison, comparison, comparison, comparison))
        yield case(dialect, tag, where, dialect == "postgres" and bool(rng.getrandbits(1)),
                   table=rng.choice(("target", "child", "control")))


def analyzed(dialect, tag):
    schema = SCHEMAS[dialect]
    relation = schema + ".ir_delete_target"
    declarations = [f"CREATE TABLE {relation} (id INTEGER, delta INTEGER)"]
    source = "ONLY " + relation + " AS r" if dialect == "postgres" else relation
    reference = "r.delta" if dialect == "postgres" else "ir_delete_target.delta"
    operator_name = "OPERATOR(pg_catalog.>=)" if dialect == "postgres" else ">="
    examples = [
        (relation, "", None, False, []),
        (relation, " WHERE FALSE", ["boolean", False], False, []),
        (relation, " WHERE id < 1", ["comparison", "<", ["slot", 0], literal(1)], False, ["id"]),
        (relation, " WHERE NULL", ["null"], False, []),
        (source, f" WHERE {reference} {operator_name} -1", ["comparison", ">=", ["slot", 0], literal(-1)],
         dialect == "postgres", ["delta"]),
        (relation, " WHERE NOT (id < 1 OR delta IS NOT NULL)",
         ["logic", "NOT", ["logic", "OR", ["comparison", "<", ["slot", 0], literal(1)], ["null-test", True, ["slot", 1]]], None],
         False, ["id", "delta"]),
    ]
    for table, suffix, where, only, columns in examples:
        yield ({"dialect": dialect, "release": tag, "sql": "DELETE FROM " + table + suffix, "declarations": declarations},
               case(dialect, tag, where, only, columns))


def negatives(dialect, tag):
    schema = SCHEMAS[dialect]
    relation = schema + ".ir_delete_target"
    base = f"CREATE TABLE {relation} (id INTEGER, delta INTEGER)"
    yield case(dialect, tag, ["comparison", "=", ["slot", 2], literal(0)]), "must belong to its target"
    yield case(dialect, tag, ["comparison", "=", literal(0), ["slot", 2]]), "must belong to its target"
    yield case(dialect, tag, ["logic", "NOT", ["null-test", False, ["slot", 0, 1]], None]), "must belong to its target"
    platform = "PostgreSql" if dialect == "postgres" else "MySql"
    diagnostics = f"SqlSemantics\\Platform\\{platform}\\Model\\Diagnostic\\"
    missing = {"dialect": dialect, "release": tag, "sql": f"DELETE FROM {relation}", "declarations": []}
    yield {**missing, "complete": False}, {
        "exception": diagnostics + "InvalidConstruction",
        "message": "The source requires a resolved, complete local relation declaration.",
    }
    kind, boundary = postgres_text_negative("delete") if dialect == "postgres" else mysql_text_negative()
    yield {
        "dialect": dialect, "release": tag, "sql": f"DELETE FROM {relation} WHERE id = 1",
        "declarations": [f"CREATE TABLE {relation} (id {kind}, delta INTEGER)"],
    }, boundary
    clause = " LIMIT 1" if dialect == "mysql" else f" USING {schema}.ir_delete_control"
    declarations = [base] if dialect == "mysql" else [base, f"CREATE TABLE {schema}.ir_delete_control (id INTEGER, delta INTEGER)"]
    yield {
        "dialect": dialect, "release": tag, "sql": f"DELETE FROM {relation}" + clause, "declarations": declarations,
    }, "Complete MySQL DELETE requires" if dialect == "mysql" else "Complete PostgreSQL DELETE requires"
    yield missing, {
        "exception": diagnostics + "SemanticException",
        "message": "Relation ir_delete_target does not exist.",
    }


def matching(where, columns, row):
    def operand(term):
        return int(term[1]) if term[0] == "integer" else row[("id", "delta").index(columns[term[1]])]

    return admits(where, operand)


def ordered(tables):
    return {key: sorted(rows, key=lambda value: json.dumps(value, ensure_ascii=False)) for key, rows in tables.items()}


def observation(value):
    require(value.get("status") == "deleted", "server-delete-observation-failed", value)
    return {**value, "after": ordered(value["after"]), "restored": ordered(value["restored"])}


def expected(item):
    role = item["target"]["table"].removeprefix("ir_delete_")
    targets = {role}
    if role == "target" and item["dialect"] == "postgres" and not item["target"]["only"]:
        targets.add("child")
    retained = {}
    removed = []
    for name, rows in ROWS.items():
        retained[name] = []
        for row in rows:
            if name in targets and matching(item["where"], item["target"]["columns"], row):
                removed.append(["ir_delete_" + name, *row])
            else:
                retained[name].append(row)
    retained["events"] = removed
    return {"status": "deleted", "columns": 0, "count": len(removed),
            "after": ordered(retained), "restored": ordered(ROWS)}


def requests(dialect, tag, specification):
    for index, item in enumerate(finite_cases(dialect, tag)):
        yield f"finite/{index}", item, item, None
    exploration = specification["exploration"]
    for seed in exploration["seeds"]:
        for index, item in enumerate(seeded(dialect, tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, meaning) in enumerate(analyzed(dialect, tag)):
        yield f"analysis/{index}", item, meaning, None
    for index, (item, signal) in enumerate(negatives(dialect, tag)):
        yield f"negative/{index}", item, None, signal
