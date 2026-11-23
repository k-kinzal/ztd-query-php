"""Compare lexical PostgreSQL VALUES through independent positional evaluation."""

from itertools import product
from random import Random
import json
import re
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import OBSERVER, main, probe
from run_server_postgres_values import (
    DIAGNOSTIC, KINDS, LITERALS, TYPES, cell, kind_at, native_kinds, observation,
    predict as evaluate_values, specifications as profiles, typed_null,
)
from run_server_postgres_with_projection import POLICIES, literal_scan, nested, read, scan
from sqlite_domain import RenderFault

LABEL = "postgres-local-values"
CONTRACT_NAME = "postgres-local-values-v2.json"
CONTRACT = "38b6cccb4b5a203d1a5e3414a924e97ba1f9977a5c8db67d396f999ed476b255"
COMPLETE_SEEDS = True
AXES = ("position", "layout", "rowBag", "kind")
PLACEMENTS = ("derived", "with-source", "with-body", "nested-with-source")
PLAN = re.compile(r'"__ztd_cte_[0-9]+" AS (?:(MATERIALIZED|NOT MATERIALIZED) )?\(')
SEMANTIC = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\SemanticException"


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-local-values-contract-mismatch")
    return json.loads(path.read_text()), profiles()[1]


def values(rows):
    return {"postgresValues": rows}


def local(definition, columns):
    return {"local": {"definition": definition, "columns": columns}}


def program(tag, tree):
    return {"dialect": "postgres", "release": tag, **tree}


def placement(position, source, columns, policy="MATERIALIZED"):
    anchor = literal_scan(8, 9)
    if position == "derived":
        return local(source, columns)
    if position == "with-source":
        return nested([[policy, source]], read(0, columns))
    if position == "with-body":
        return nested([[policy, anchor]], source)
    inner = nested([[policy, anchor]], source)
    return nested([["", inner]], read(0, columns))


def finite(tag, position, layout, bag, kind):
    kinds = [kind, kind_at(kind, 1)]
    rows = [[cell(candidate, LITERALS[candidate][-1]) for candidate in kinds]]
    if bag == "duplicated":
        rows.append(rows[0])
    elif bag == "nullable":
        rows.insert(0, [["null"], ["null"]])
    order = [0, 1] if layout == "forward" else [1, 0]
    if position == "with-body":
        rows = [[row[slot] for slot in order] for row in rows]
    tree = placement(position, values(rows), [[slot, name] for slot, name in zip(order, ("one", "two"))])
    return program(tag, tree)


def finite_ids(_, spec, _tag):
    result = set(product(*(spec["finite"][axis] for axis in AXES)))
    require(len(result) == spec["finite"]["identities"], "postgres-local-values-grid")
    return result


def locate(tree):
    if "local" in tree:
        query = tree["local"]
        if "with" in query["definition"]:
            return "derived-with-body", query["definition"]["with"]["body"], query["columns"]
        return "derived", query["definition"], query["columns"]
    query = tree["with"]
    final = query["body"]
    if "postgresValues" in final:
        return "with-body", final, None
    slot, columns = final["read"]
    definition = query["definitions"][slot][1]
    if "with" in definition:
        return "nested-with-source", definition["with"]["body"], columns
    return "with-source", definition, columns


def finite_id(_, actual, _item):
    position, operand, columns = locate(actual)
    rows = operand["postgresValues"]
    kinds = native_kinds(operand)
    if position == "with-body":
        forward = kind_at(kinds[0], 1) == kinds[1]
        kind = kinds[0] if forward else kinds[1]
    else:
        forward = columns[0][0] == 0
        kind = kinds[0]
    bag = "single" if len(rows) == 1 else ("duplicated" if rows[0] == rows[1] else "nullable")
    return position, "forward" if forward else "reverse", bag, kind


def request(tag, sql):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": []}


def analyzed(tag):
    sample = values([[cell("integer", "5"), cell("boolean", True)], [["null"], cell("boolean", False)]])
    cols = [[1, "b"], [0, "a"]]
    yield "SELECT q.column2 AS b,q.column1 AS a FROM (VALUES (5,TRUE),(NULL,FALSE)) AS q", program(tag, local(sample, cols))
    yield "SELECT q.b, q.a FROM (VALUES (5,TRUE),(NULL,FALSE)) AS q(a,b)", program(tag, local(sample, cols))
    yield "WITH q(a,b) AS (VALUES (5,TRUE),(NULL,FALSE)) SELECT b,a FROM q", program(tag, nested([["", sample]], read(0, cols)))
    duplicates = values([[cell("integer", "5")], [cell("integer", "5")]])
    yield "WITH q AS MATERIALIZED (VALUES (5),(5)) SELECT column1 AS n FROM q", program(
        tag, nested([["MATERIALIZED", duplicates]], read(0, [[0, "n"]])))
    nullable = values([[typed_null("bigint")], [cell("integer", "4")]])
    yield "WITH q AS NOT MATERIALIZED (VALUES (CAST(NULL AS bigint)),(4)) SELECT column1 AS n FROM q", program(
        tag, nested([["NOT MATERIALIZED", nullable]], read(0, [[0, "n"]])))
    anchor = literal_scan(8, 9)
    independent = nested([["", anchor]], duplicates)
    yield "WITH unused AS (SELECT 8 AS id,9 AS wide) VALUES (5),(5)", program(tag, independent)
    yield "SELECT q.column1 AS n FROM (WITH unused AS (SELECT 8 AS id,9 AS wide) VALUES (5),(5)) AS q", program(
        tag, local(independent, [[0, "n"]]))
    read_a = read(0, [[1, "flag"], [0, "n"]])
    yield "WITH a AS (VALUES (5,TRUE),(NULL,FALSE)), b AS (SELECT column2 AS flag,column1 AS n FROM a) SELECT n,flag FROM b", program(
        tag, nested([["", sample], ["", read_a]], read(1, [[1, "n"], [0, "flag"]])))


def binding_case(tag):
    unused = scan(None, [[["parameter", 1], "ignored"]], source=False)
    body = values([[cell("integer", "4")], [cell("integer", "4")], [["null"]]])
    return {**program(tag, nested([["", unused]], body)), "bindings": [19]}


def negatives(tag):
    source = values([[cell("integer", "5")]])
    yield program(tag, local(source, [[1, "n"]])), DIAGNOSTIC, "The local slot must belong to the definition."
    yield program(tag, nested([["", source]], read(0, [[1, "n"]]))), DIAGNOSTIC, "A local read must select an existing WITH output slot."
    other = "pg-16.6" if tag == "pg-17.2" else "pg-17.2"
    yield program(tag, nested([["", {**source, "release": other}]], read(0, [[0, "n"]]))), DIAGNOSTIC, "Each WITH query must use its owning language profile."
    yield request(tag, "SELECT q.column1 FROM LATERAL (VALUES (1)) q"), DIAGNOSTIC, "The local definition cannot depend on a lateral scope."
    yield request(tag, "SELECT q.missing FROM (VALUES (1)) q"), SEMANTIC, "missing"
    yield request(tag, "SELECT q.column1 FROM (VALUES (1)) q ORDER BY 1"), DIAGNOSTIC, "The local projection requires an ordinary result without outer clauses."


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        width = 1 + rng.randrange(4)
        kinds = [rng.choice(KINDS) for _ in range(width)]
        rows = []
        for row in range(1 + rng.randrange(4)):
            cells = []
            for kind in kinds:
                flag = rng.randrange(4)
                effective = "integer" if kind == "bigint" and flag == 2 else kind
                cells.append(["null"] if flag == 0 else (typed_null(kind) if flag == 1 else cell(effective, rng.choice(LITERALS[effective]))))
            rows.append(cells)
        if index % 4 == 0 and len(rows) > 1:
            rows[-1] = rows[0]
        columns = [[rng.randrange(width), f"field {slot}"] for slot in range(1 + rng.randrange(4))]
        pos = PLACEMENTS[index % len(PLACEMENTS)]
        policy = tuple(POLICIES.values())[rng.randrange(len(POLICIES))]
        tree = placement(pos, values(rows), columns, policy)
        if pos == "with-source" and index % 3 == 1:
            tree["with"]["definitions"].insert(0, ["", literal_scan()])
            tree["with"]["body"]["read"][0] = 1
        if pos == "derived" and index % 3 == 0:
            tree = local(nested([[policy, literal_scan()]], values(rows)), columns)
        yield program(tag, tree)


def requests(_, tag, spec):
    for index, dimensions in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = finite(tag, *dimensions)
        yield f"finite/{index}", item, item, None
    authored = list(analyzed(tag))
    require(len(authored) == len(spec["analysis"]), "postgres-local-values-analysis-count")
    for index, (sql, item) in enumerate(authored):
        yield f"analyze/{index}", request(tag, sql), item, None
    require(len(spec["bindingProbes"]) == 1, "postgres-local-values-binding-count")
    binding = binding_case(tag)
    yield "binding/direct-unused", binding, binding, None
    yield "binding/analyzed-unused", request(tag, "WITH unused AS (SELECT CAST($1 AS integer) AS ignored) VALUES (4),(4),(NULL)"), binding, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    invalid = list(negatives(tag))
    require(len(invalid) == len(spec["negative"]), "postgres-local-values-negative-count")
    for index, (item, exception, message) in enumerate(invalid):
        yield f"negative/{index}", item, None, {"exception": exception, "message": message}


def result(node, bindings, scope=()):
    if "postgresValues" in node:
        return evaluate_values(node)
    if "with" in node:
        query = node["with"]
        visible = []
        for _, definition in query["definitions"]:
            visible.append(result(definition, bindings, visible))
        return result(query["body"], bindings, visible)
    if "read" in node or "local" in node:
        if "read" in node:
            offset, columns = node["read"]
            source = scope[offset]
        else:
            columns = node["local"]["columns"]
            source = result(node["local"]["definition"], bindings)
        return {"status": "rows", "fields": [[label, *source["fields"][slot][1:]] for slot, label in columns],
                "rows": [[row[slot] for slot, _ in columns] for row in source["rows"]]}
    require(node.get("source") is None and node["where"] is None, "postgres-local-values-oracle-source")
    row = [bindings[term[1] - 1] if term[0] == "parameter" else int(term[1]) for term, _ in node["columns"]]
    return {"status": "rows", "fields": [[label, *TYPES["integer"]] for _, label in node["columns"]], "rows": [row]}


def graph(node):
    if "postgresValues" in node:
        return {"postgresValues": node["postgresValues"], "columnKinds": [TYPES[kind][0] for kind in native_kinds(node)]}
    if "with" in node:
        query = node["with"]
        return nested([[mode, graph(member)] for mode, member in query["definitions"]], graph(query["body"]))
    if "local" in node:
        return local(graph(node["local"]["definition"]), node["local"]["columns"])
    if "read" in node:
        return {"read": node["read"]}
    return {key: node[key] for key in ("source", "columns", "where", "orderBy", "limit", "distinct")}


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            "postgres-local-values-rejected", reply)
    expected = graph(item)
    same(expected, {key: reply[key] for key in expected})


def policies(node):
    result = []
    if "with" in node:
        for policy, definition in node["with"]["definitions"]:
            result.extend([policy, *policies(definition)])
        result.extend(policies(node["with"]["body"]))
    elif "local" in node:
        result.extend(policies(node["local"]["definition"]))
    return result


def expected(item):
    rows = result(item, item.get("bindings", []))
    return {**rows, **({"inputOids": ["23"]} if "bindings" in item else {})}


def check_observation(item, actual, sql):
    predicted = observation(expected(item))
    witness = {"expected": predicted, "observed": actual, "sql": sql}
    require(actual["status"] == "rows", "postgres-local-values-native-rejection", witness)
    require('"__ztd_cte_' not in sql or PLAN.findall(sql) == policies(item), "postgres-local-values-materialization", witness)
    try:
        same(predicted, actual)
    except Failure as error:
        raise Failure(error.signal, witness) from error


def observer_request(item, sql):
    return {"sql": sql, "observePostgresTypeIds": True,
            **({"withBindings": item["bindings"]} if "bindings" in item else {})}


def prepare(_, _db):
    return {}


def check_preparation(_, _env, preparation):
    same({}, preparation)


def sample_forms(forms, reply, _, _item):
    pos, operand, columns = locate(reply)
    types = native_kinds(operand)
    forms["placement/" + pos] += 1
    forms[f"width/{len(types)}"] += 1
    for kind in types:
        forms["kind/" + kind] += 1
    for policy in policies(reply):
        forms["policy/" + policy] += 1
    rows = operand["postgresValues"]
    if len({json.dumps(row) for row in rows}) < len(rows):
        forms["duplicate-rows"] += 1
    if any(term[0] in ("null", "builtin-cast") for row in rows for term in row):
        forms["null"] += 1
    if columns and len({slot for slot, _ in columns}) < len(columns):
        forms["duplicate-positions"] += 1
    if columns and [slot for slot, _ in columns] != sorted(slot for slot, _ in columns):
        forms["reordered-positions"] += 1
    if "with" in reply and len(reply["with"]["definitions"]) > 1:
        forms["multiple-definitions"] += 1
    for column in zip(*rows):
        if {"integer", "wide-integer"} <= {term[0] for term in column}:
            forms["mixed-signed-widths"] += 1


def required_forms(*_):
    return {*(f"placement/{name}" for name in (*PLACEMENTS, "derived-with-body")), *(f"kind/{kind}" for kind in KINDS),
            *(f"policy/{name}" for name in POLICIES.values()), "width/1", "width/4", "duplicate-rows", "null",
            "duplicate-positions", "reordered-positions", "multiple-definitions", "mixed-signed-widths"}


def controls():
    item = finite("pg-17.2", "with-source", "reverse", "duplicated", "integer")
    healthy = observation(expected(item))
    check_observation(item, healthy, "control SQL")
    changed = {**healthy, "rows": healthy["rows"][:-1]}
    try:
        check_observation(item, changed, "missing one duplicate")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"discarded-duplicate": {"input": item, **error.detail}}
    raise Failure("postgres-local-values-comparator-accepted")


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "postgres-local-values-mutant-response", reply)
    raise Failure("postgres-local-values-invalid-published", {"sql": reply["sql"], "observed": reply, "expected": item["boundary"]})


def fault_probe(item, reply, db):
    if "boundary" in item:
        boundary(item, reply)
    else:
        probe(item, reply, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            boundary(rule.item, record["healthyResponse"])
            boundary(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            same(observation(expected(rule.item)), witness["expected"])
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-local-values-missed-fault", rule.name)


def faults(_, tag):
    root = "sql-semantics-postgres/src/Ir/Rows"
    item = finite(tag, "with-source", "reverse", "nullable", "integer")
    malformed, exception, message = list(negatives(tag))[1]
    scalar = program(tag, nested([["", values([[cell("integer", "6")]])]], read(0, [[0, "answer"]])))
    return [
        RenderFault("values-use-wrong-lexical-field", f"{root}/With/WithSql.php",
                    "$renderer->localColumn($column->slot)", "$renderer->localColumn(0)", item, "meaning-mismatch"),
        RenderFault("values-swap-synthetic-aliases", f"{root}/Values/ValuesSql.php",
                    "implode(', ', $names) . ')';", "implode(', ', array_reverse($names)) . ')';",
                    item, "meaning-mismatch"),
        RenderFault("values-replace-source-table", f"{root}/Values/ValuesSql.php",
                    "$query->toString()", "'VALUES (CAST(910 AS pg_catalog.int4))'", scalar, "meaning-mismatch"),
        RenderFault("values-ignore-lexical-width", f"{root}/With/WithScope.php",
                    "$column->slot < $width,", "true,", {"boundary": {"exception": exception, "message": message}},
                    "postgres-local-values-invalid-published", malformed),
        RenderFault("values-erase-materialization", f"{root}/With/WithSql.php",
                    "$policy = $definition->materialization->value;", "$policy = '';",
                    item, "postgres-local-values-materialization"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
