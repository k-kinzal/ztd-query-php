"""Compare PostgreSQL VALUES set trees with an independent typed multiset model."""

from collections import Counter
from itertools import product
from random import Random
import json
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import OBSERVER, main, probe
from run_server_postgres_local_values import graph as leaf_graph, program, request, values
from run_server_postgres_values import (
    BY_NATIVE, BY_TAG, DIAGNOSTIC, KINDS, LITERALS, TYPES, cell, kind_at, observation,
    predict as evaluate_values, specifications as profiles, typed_null,
)
from run_server_postgres_with_projection import scan
from sqlite_domain import RenderFault

LABEL = "postgres-values-compound"
CONTRACT_NAME = "postgres-values-compound-v1.json"
CONTRACT = "5593e5a6626ca8cf678d51470a767384ac649469f1823ae1251efde76ccf64e2"
COMPLETE_SEEDS = True
AXES = ("operator", "all", "placement", "kind", "layout")
OPERATORS = ("UNION", "INTERSECT", "EXCEPT")
PLACEMENTS = ("left", "right", "both")
SEMANTIC = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\SemanticException"


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-values-compound-contract-mismatch")
    return json.loads(path.read_text()), profiles()[1]


def compound(left, operator, all_rows, right):
    return {"compound": {"left": left, "operator": operator, "all": all_rows, "right": right}}


def selected(terms, labels):
    return scan(None, [[term, name] for term, name in zip(terms, labels)], source=False)


def constant(terms, is_values, side):
    if is_values:
        return values([terms, terms, [["null"] for _ in terms]])
    return selected(terms, [f"{side}{index + 1}" for index in range(len(terms))])


def finite(tag, operator, all_rows, placement, native, layout):
    left = [cell(native, LITERALS[native][-1]), cell(kind_at(native, 1), LITERALS[kind_at(native, 1)][-1])]
    right = left if layout == "matching" else [cell(native, LITERALS[native][0]), left[1]]
    return program(tag, compound(constant(left, placement in ("left", "both"), "lhs"), operator, all_rows,
                                constant(right, placement in ("right", "both"), "rhs")))


def finite_ids(_, spec, _tag):
    grid = set(product(*(spec["finite"][axis] for axis in AXES)))
    require(len(grid) == spec["finite"]["identities"], "postgres-values-compound-grid")
    return grid


def first_cell(node):
    return node["postgresValues"][0][0] if "postgresValues" in node else node["columns"][0][0]


def finite_id(_, reply, _item):
    branch = reply["compound"]
    first, second = branch["left"], branch["right"]
    placement = "both" if all("postgresValues" in child for child in (first, second)) else (
        "left" if "postgresValues" in first else "right")
    initial, later = first_cell(first), first_cell(second)
    return branch["operator"], branch["all"], placement, BY_TAG[initial[0]], "matching" if initial == later else "different"


def analyzed(tag):
    three = cell("integer", "3")
    seven = cell("integer", "7")
    flag = cell("boolean", True)
    yield "VALUES (3),(3) UNION ALL VALUES (7)", program(tag, compound(values([[three], [three]]), "UNION", True, values([[seven]])))
    yield "VALUES (3) UNION SELECT 7 AS renamed", program(tag, compound(values([[three]]), "UNION", False, selected([seven], ["renamed"])))
    yield "SELECT TRUE AS found INTERSECT ALL VALUES (TRUE),(TRUE)", program(
        tag, compound(selected([flag], ["found"]), "INTERSECT", True, values([[flag], [flag]])))
    yield "VALUES (3),(3) EXCEPT ALL SELECT 3 AS ignored", program(
        tag, compound(values([[three], [three]]), "EXCEPT", True, selected([three], ["ignored"])))
    yield "SELECT 3 AS expanded UNION ALL VALUES (4294967296),(NULL)", program(
        tag, compound(selected([three], ["expanded"]), "UNION", True, values([[cell("bigint", "4294967296")], [["null"]]])))
    yield "VALUES (NULL),(NULL) UNION ALL SELECT CAST('native' AS pg_catalog.text) AS label", program(
        tag, compound(values([[["null"]], [["null"]]]), "UNION", True, selected([cell("text", "native")], ["label"])))
    child = compound(values([[three]]), "EXCEPT", True, values([[three]]))
    yield "VALUES (3) EXCEPT ALL (VALUES (3) EXCEPT ALL VALUES (3))", program(tag, compound(values([[three]]), "EXCEPT", True, child))
    yield "(VALUES (3),(7))", program(tag, values([[three], [seven]]))


def negatives(tag):
    one = values([[cell("integer", "1")]])
    wide = values([[cell("integer", "1"), cell("integer", "2")]])
    yield program(tag, compound(one, "UNION", False, wide)), DIAGNOSTIC, "Compound operands must have the same width."
    flags = values([[cell("boolean", True)]])
    yield program(tag, compound(one, "UNION", False, flags)), DIAGNOSTIC, "PostgreSQL compound output types are incompatible."
    unknown = values([[["null"]], [["null"]]])
    yield program(tag, compound(unknown, "UNION", True, one)), DIAGNOSTIC, "PostgreSQL compound output types are incompatible."
    other = "pg-16.6" if tag == "pg-17.2" else "pg-17.2"
    yield program(tag, compound(one, "UNION", True, {**one, "release": other})), DIAGNOSTIC, "Compound operands must have the same language profile."
    ordered = {**selected([cell("integer", "1")], ["a"]), "orderBy": [[1, False, False]]}
    yield program(tag, compound(one, "UNION", True, ordered)), DIAGNOSTIC, "A compound arm requires an unbounded projection without ordering."
    yield request(tag, "VALUES (NULL) UNION VALUES (DEFAULT)"), DIAGNOSTIC, "The operand has no complete PostgreSQL projection representation."


def binding_case(tag):
    return {**program(tag, compound(selected([["parameter", 1]], ["p"]), "UNION", True, values([[cell("integer", "4")]]))),
            "bindings": [61]}


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        width = 1 + rng.randrange(3)
        kinds = [rng.choice(KINDS) for _ in range(width)]

        def value_row(first):
            row = []
            for declared in kinds:
                flag = rng.randrange(5)
                if flag == 0 and not first:
                    row.append(["null"])
                elif flag <= 1:
                    row.append(typed_null(declared))
                else:
                    kind = "integer" if flag == 2 and declared == "bigint" else declared
                    row.append(cell(kind, rng.choice(LITERALS[kind])))
            return row

        def leaf(as_values, prefix):
            initial = value_row(True)
            if not as_values:
                return selected(initial, [f"{prefix} field {position + 1}" for position in range(width)])
            rows = [initial, *[value_row(False) for _ in range(rng.randrange(3))]]
            if index % 4 == 0:
                rows.append(initial)
            return values(rows)

        pos = PLACEMENTS[index % len(PLACEMENTS)]
        first, second = leaf(pos in ("left", "both"), "before"), leaf(pos in ("right", "both"), "after")
        root = compound(first, rng.choice(OPERATORS), bool(rng.randrange(2)), second)
        if index % 2 == 0:
            root = compound(root, rng.choice(OPERATORS), bool(rng.randrange(2)), leaf(True, "right"))
        elif index % 3 == 1:
            root = compound(leaf(True, "left"), rng.choice(OPERATORS), bool(rng.randrange(2)), root)
        yield program(tag, root)


def requests(_, tag, spec):
    for index, axes in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = finite(tag, *axes)
        yield f"finite/{index}", item, item, None
    written = list(analyzed(tag))
    require(len(written) == len(spec["analysis"]), "postgres-values-compound-analysis-count")
    for index, (sql, item) in enumerate(written):
        yield f"analyze/{index}", request(tag, sql), item, None
    binding = binding_case(tag)
    yield "binding/direct", binding, binding, None
    yield "binding/analyzed", request(tag, "SELECT CAST($1 AS integer) AS p UNION ALL VALUES (4)"), binding, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    malformed = list(negatives(tag))
    require(len(malformed) == len(spec["negative"]), "postgres-values-compound-negative-count")
    for index, (item, exception, message) in enumerate(malformed):
        yield f"negative/{index}", item, None, {"exception": exception, "message": message}


def kind_of(term):
    if term[0] == "builtin-cast":
        return BY_NATIVE[term[1]]
    return "integer" if term[0] == "parameter" else BY_TAG[term[0]]


def literal_value(term, binding):
    if term[0] == "builtin-cast":
        return None
    if term[0] == "parameter":
        return binding[term[1] - 1]
    return int(term[1]) if term[0] in ("integer", "wide-integer") else term[1]


def common(left, right):
    if left == right:
        return left
    require({left, right} == {"integer", "bigint"}, "postgres-values-compound-oracle-kinds", [left, right])
    return "bigint"


def result(node, bindings):
    if "postgresValues" in node:
        rows = evaluate_values(node)
        headings = [name for name, _, _ in rows["fields"]]
        kinds = [BY_NATIVE[kind] for _, kind, _ in rows["fields"]]
        return headings, kinds, Counter(map(json.dumps, rows["rows"]))
    if "compound" not in node:
        require(node["source"] is None and node["where"] is None, "postgres-values-compound-oracle-source")
        labels = [name for _, name in node["columns"]]
        kinds = [kind_of(term) for term, _ in node["columns"]]
        row = [literal_value(term, bindings) for term, _ in node["columns"]]
        return labels, kinds, Counter({json.dumps(row): 1})
    branch = node["compound"]
    names, left_kinds, left = result(branch["left"], bindings)
    _, right_kinds, right = result(branch["right"], bindings)
    require(len(left_kinds) == len(right_kinds), "postgres-values-compound-oracle-width")
    kinds = [common(a, b) for a, b in zip(left_kinds, right_kinds)]
    if not branch["all"]:
        left, right = Counter(left.keys()), Counter(right.keys())
    operator = branch["operator"]
    if operator == "UNION":
        bag = left + right if branch["all"] else left | right
    elif operator == "INTERSECT":
        bag = left & right
    else:
        require(operator == "EXCEPT", "postgres-values-compound-oracle-operator")
        bag = left - right
    return names, kinds, bag


def expected(item):
    names, kinds, bag = result(item, item.get("bindings", []))
    return {"status": "rows", "fields": [[name, *TYPES[kind]] for name, kind in zip(names, kinds)],
            "rows": list(map(json.loads, bag.elements())),
            **({"inputOids": ["23"]} if "bindings" in item else {})}


def graph(node):
    if "compound" not in node:
        return leaf_graph(node)
    branch = node["compound"]
    return compound(graph(branch["left"]), branch["operator"], branch["all"], graph(branch["right"]))


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            "postgres-values-compound-rejected", reply)
    shape = graph(item)
    same(shape, {key: reply[key] for key in shape})


def check_observation(item, actual, sql):
    prediction = observation(expected(item))
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual["status"] == "rows", "postgres-values-compound-native-rejection", witness)
    try:
        same(prediction, actual)
    except Failure as error:
        raise Failure(error.signal, witness) from error


def observer_request(item, sql):
    return {"sql": sql, "observePostgresTypeIds": True,
            **({"postgresSetBindings": item["bindings"]} if "bindings" in item else {})}


def prepare(_, _db):
    return {}


def check_preparation(_, _environment, prepared):
    same({}, prepared)


def sample_forms(forms, reply, _, _item):
    def visit(node):
        if "compound" in node:
            branch = node["compound"]
            forms["operator/" + branch["operator"]] += 1
            forms[f"all/{branch['all']}"] += 1
            for side in ("left", "right"):
                child = branch[side]
                if "compound" in child:
                    forms["nested/" + side] += 1
                if "postgresValues" in child:
                    forms["values/" + side] += 1
                visit(child)
        elif "postgresValues" in node:
            forms[f"width/{len(node['columnKinds'])}"] += 1
            for kind in node["columnKinds"]:
                forms["kind/" + BY_NATIVE[kind]] += 1
            rows = node["postgresValues"]
            if len(set(map(json.dumps, rows))) < len(rows):
                forms["duplicate"] += 1
            if any(term[0] in ("null", "builtin-cast") for row in rows for term in row):
                forms["null"] += 1
        else:
            forms["select"] += 1
    visit(reply)


def required_forms(*_):
    return {*(f"operator/{name}" for name in OPERATORS), *(f"kind/{kind}" for kind in KINDS),
            "all/True", "all/False", "nested/left", "nested/right", "values/left", "values/right",
            "width/1", "width/2", "width/3", "duplicate", "null", "select"}


def controls():
    item = finite("pg-17.2", "UNION", True, "both", "integer", "matching")
    ok = observation(expected(item))
    check_observation(item, ok, "set control")
    bad = {**ok, "rows": ok["rows"][:-1]}
    try:
        check_observation(item, bad, "lost multiplicity")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"lost-duplicate": {"input": item, **error.detail}}
    raise Failure("postgres-values-compound-accepted-duplicate-loss")


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "postgres-values-compound-mutant-response", reply)
    raise Failure("postgres-values-compound-invalid-published", {"sql": reply["sql"], "observed": reply, "expected": item["boundary"]})


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
    raise Failure("postgres-values-compound-undetected-fault", rule.name)


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Rows"
    invalid, exception, message = list(negatives(tag))[2]
    one = values([[cell("integer", "1")]])
    big = values([[cell("bigint", "4")]])
    group = program(tag, compound(one, "EXCEPT", True, compound(one, "EXCEPT", True, one)))
    return [
        RenderFault("values-set-swap-operands", f"{path}/Compound.php",
                    "$left . ' ' . $this->operator->value . ($this->all ? ' ALL ' : ' ') . CompoundShape::sql($this->right)",
                    "CompoundShape::sql($this->right) . ' ' . $this->operator->value . ($this->all ? ' ALL ' : ' ') . $left",
                    finite(tag, "EXCEPT", True, "left", "integer", "different"), "meaning-mismatch"),
        RenderFault("values-set-discard-duplicates", f"{path}/Compound.php",
                    "$this->all ? ' ALL ' : ' '", "false ? ' ALL ' : ' '",
                    finite(tag, "UNION", True, "both", "integer", "matching"), "meaning-mismatch"),
        RenderFault("values-set-erase-right-group", f"{path}/CompoundShape.php",
                    "return $query instanceof Compound ? '(' . $sql . ')' : $sql;", "return $sql;", group, "meaning-mismatch"),
        RenderFault("values-set-narrow-bigint", f"{path}/Values/ValuesCell.php",
                    "return (new SelectSql())->expression($this->value, null);",
                    "return str_replace('pg_catalog.int8', 'pg_catalog.int4', (new SelectSql())->expression($this->value, null));",
                    program(tag, compound(one, "UNION", True, big)), "meaning-mismatch"),
        RenderFault("values-set-merge-incompatible-kinds", f"{path}/CompoundShape.php",
                    "$right === ResultKind::Unknown || $left === $right,", "true,",
                    {"boundary": {"exception": exception, "message": message}}, "postgres-values-compound-invalid-published", invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
