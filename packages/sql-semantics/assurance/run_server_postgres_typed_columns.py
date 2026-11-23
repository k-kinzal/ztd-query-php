"""Compare native PostgreSQL physical field types and exact occurrence coordinates."""

from itertools import product
import json
import random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, check_preparation, main, observer_request
from server_cases import observation
from server_join_cases import case, compare, expected as join_expected, is_join, join, leaves, slot
from sqlite_domain import RenderFault

LABEL = "postgres-typed-columns"
CONTRACT = "8454694fe46150d128c458feed1641860849c2a1a08c1ff53eaf938f714f6a7a"
LEFT, RIGHT = "ir_typed_left", "ir_typed_right"
HEADINGS = {
    LEFT: ("id", "label", "weight", "note"),
    RIGHT: ("note", "weight", "label", "id"),
}
KINDS = {"id": "int4", "weight": "int4", "label": "text", "note": "text"}
RAW = {
    LEFT: [(1, "left 'α", 101, "left memo"), (1, "left 'α", 101, "left memo"),
           (2, None, 102, "line\n2"), (3, "left '" + chr(92), None, None), (None, "", 103, "unmatched null")],
    RIGHT: [(1, "right 値", 201, "right memo"), (2, "right \t", 202, ""), (2, "right \t", 202, ""),
            (5, None, 203, "right only"), (None, "null key", 204, None)],
}
DESCENDANTS = {LEFT: [(5, "left child", 801, "from child")], RIGHT: [(3, "right child", 901, "from child")]}
DECLARATIONS = [f"CREATE TABLE public.{table} (" + ", ".join(f"{name} pg_catalog.{KINDS[name]}" for name in fields) + ")"
                for table, fields in HEADINGS.items()]
DIAGNOSTICS = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\"


def specifications():
    path, profile = TOOL / "postgres-typed-columns-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "postgres-typed-columns-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def rows_in_heading(table, data):
    base = HEADINGS[LEFT]
    return [tuple(row[base.index(name)] for name in HEADINGS[table]) for row in data]


def prepare(_, db):
    fixture = [f"DROP TABLE IF EXISTS public.{table}_child" for table in HEADINGS]
    fixture += [f"DROP TABLE IF EXISTS public.{table}" for table in HEADINGS] + DECLARATIONS

    def sql_atom(value):
        if value is None:
            return "NULL"
        if isinstance(value, int):
            return str(value)
        return f"pg_catalog.convert_from(pg_catalog.decode('{value.encode().hex()}','hex'),'UTF8')"

    for table in HEADINGS:
        child = f"{table}_child"
        fixture.append(f"CREATE TABLE public.{child} () INHERITS (public.{table})")
        for relation, data in ((table, RAW[table]), (child, DESCENDANTS[table])):
            rows = ",".join("(" + ",".join(map(sql_atom, row)) + ")" for row in rows_in_heading(table, data))
            fixture.append(f"INSERT INTO public.{relation} VALUES {rows}")
    result = db.request({"fixture": fixture})
    same({"status": "fixture"}, result)
    return result


def table(name, only=False):
    heading = HEADINGS[name]
    return {"schema": "public", "table": name, "columns": list(heading), "only": only,
            "columnKinds": [KINDS[column] for column in heading]}


def field(inputs, occurrence, name):
    number = HEADINGS[inputs[occurrence]].index(name)
    return ["text-slot", number, occurrence] if KINDS[name] == "text" else slot(number, occurrence)


def query(tag, kind, inputs, output, only=False):
    sources = [table(name, only if index == 0 else False) for index, name in enumerate(inputs)]
    source = sources[0] if kind == "ordinary" else join(
        kind, *sources, None if kind == "CROSS" else compare("=", field(inputs, 0, "id"), field(inputs, 1, "id")))
    columns = [[field(inputs, occurrence, name), label] for occurrence, name, label in output]
    return case("postgres", tag, source, columns)


def finite(tag, source, mode, policy, orientation):
    inputs = (LEFT, RIGHT) if orientation == "forward" else (RIGHT, LEFT)
    if source == "ordinary":
        inputs = inputs[:1]
    occurrence = len(inputs) - 1
    kind = {"ordinary": "ordinary", "left-join": "LEFT", "right-join": "RIGHT"}[source]
    output = {
        "text": [(occurrence, "label", "value")],
        "integer": [(occurrence, "weight", "value")],
        "mixed": [(occurrence, "label", "value"), (0, "id", "number")],
        "repeated": [(occurrence, "label", "value"), (occurrence, "label", "again")],
    }[mode]
    return query(tag, kind, inputs, output, only=policy == "only")


def physical(inputs, occurrence):
    return [(occurrence, name, name) for name in HEADINGS[inputs[occurrence]]]


def analyzed(tag):
    l, r = (f"public.{name}" for name in (LEFT, RIGHT))
    one, both = (LEFT,), (LEFT, RIGHT)
    yield f"SELECT label,a.label,id FROM {l} a", query(
        tag, "ordinary", one, [(0, "label", "label"), (0, "label", "label"), (0, "id", "id")])
    yield f"SELECT * FROM {l}", query(tag, "ordinary", one, physical(one, 0))
    yield f"SELECT b.* FROM {l} a RIGHT JOIN {r} b ON a.id=b.id", query(tag, "RIGHT", both, physical(both, 1))
    yield f"SELECT a.label AS left_text,b.label AS right_text,a.id FROM {l} a LEFT JOIN {r} b ON a.id=b.id", query(
        tag, "LEFT", both, [(0, "label", "left_text"), (1, "label", "right_text"), (0, "id", "id")])
    yield f"SELECT * FROM {l} a RIGHT JOIN {r} b ON a.id=b.id", query(
        tag, "RIGHT", both, physical(both, 0) + physical(both, 1))
    yield f"SELECT a.note FROM ONLY {l} a", query(tag, "ordinary", one, [(0, "note", "note")], only=True)


def analysis_request(tag, sql):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": DECLARATIONS}


def negatives(tag):
    def witness():
        return finite(tag, "ordinary", "text", "only", "forward")

    shorter = witness()
    shorter["source"]["columnKinds"] = ["int4"]
    yield shorter, "Every table source field needs one native column kind."
    wrong_text = witness()
    wrong_text["columns"][0][0] = ["text-slot", 0, 0]
    yield wrong_text, "A text slot must belong to the SELECT source with its native kind."
    wrong_integer = witness()
    wrong_integer["columns"][0][0] = slot(1)
    yield wrong_integer, "Column slot must belong to the SELECT source."
    wrong_predicate = witness()
    wrong_predicate["where"] = compare("=", slot(1), ["integer", "1"])
    yield wrong_predicate, "visible input occurrence"
    yield {"dialect": "postgres", "release": tag, "target": table(LEFT), "where": None,
           "assignments": [[slot(1), ["integer", "4"]]]}, "An UPDATE write slot must belong to the target."


def requests(_, tag, spec):
    b = spec["finite"]
    for index, coordinates in enumerate(product(b["sources"], b["projections"], b["policy"], b["orientation"])):
        item = finite(tag, *coordinates)
        yield f"finite/{index}", item, item, None
    scenarios = list(analyzed(tag))
    require(len(scenarios) == len(spec["analysis"]), "postgres-typed-columns-analysis-incomplete")
    for index, (sql, item) in enumerate(scenarios):
        yield f"analyze/{index}", analysis_request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        rng = random.Random(seed)
        for index in range(spec["exploration"]["casesPerSeed"]):
            kind = rng.choice(("ordinary", "LEFT", "RIGHT", "CROSS"))
            inputs = tuple(rng.choices(tuple(HEADINGS), k=1 if kind == "ordinary" else 2))
            offered = [entry for i in range(len(inputs)) for entry in physical(inputs, i)]
            fields = [(*entry[:2], rng.choice((entry[2], '値 " label', "same"))) for entry in rng.choices(offered, k=rng.randint(1, 7))]
            item = query(tag, kind, inputs, fields, only=bool(rng.getrandbits(1)))
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": reason}


def graph(item):
    def address(term, scope):
        tag, ordinal, occurrence = term[0], term[1], term[2] if len(term) == 3 else 0
        source = scope[occurrence]
        physical = source["columns"][ordinal]
        expected = "text" if tag == "text-slot" else "int4"
        require(tag in ("slot", "text-slot") and KINDS[physical] == expected, "postgres-typed-columns-reference-kind", term)
        return tag, occurrence, physical

    def tree(t):
        if not is_join(t):
            kinds = t.get("columnKinds", ["int4"] * len(t["columns"]))
            require(len(kinds) == len(t["columns"]) and t["table"] in HEADINGS
                    and all(name in HEADINGS[t["table"]] and KINDS[name] == kind for name, kind in zip(t["columns"], kinds)),
                    "postgres-typed-columns-layout", t)
            return t["schema"], t["table"], t["only"]
        children = tree(t["left"]), tree(t["right"])
        if t["on"] is None:
            return t["kind"], *children, None
        tag, operator, a, b = t["on"]
        require(tag == "comparison", "postgres-typed-columns-invalid-condition", t["on"])
        scope = leaves(t)
        return t["kind"], *children, (operator, address(a, scope), address(b, scope))

    source = item["source"]
    return tree(source), tuple((address(term, leaves(source)), label) for term, label in item["columns"])


def finite_id(_, reply, _item):
    tree, fields = graph(reply)
    member = tree[1] if is_join(reply["source"]) else tree
    source = {"public": "ordinary", "LEFT": "left-join", "RIGHT": "right-join"}[tree[0]]
    roles = tuple(value[0] for value, _ in fields)
    mode = {("text-slot",): "text", ("slot",): "integer", ("text-slot", "slot"): "mixed",
            ("text-slot", "text-slot"): "repeated"}[roles]
    return source, mode, "only" if member[2] else "include", "forward" if member[1] == LEFT else "reversed"


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    coords = {(op, p, pol, orient) for op in b["sources"] for p in b["projections"]
              for pol in b["policy"] for orient in b["orientation"]}
    require(len(coords) == b["identities"], "wrong-postgres-typed-columns-domain", len(coords))
    return coords


def heading_and_rows(source):
    name = source["table"]
    data = RAW[name] + ([] if source["only"] else DESCENDANTS[name])
    return HEADINGS[name], rows_in_heading(name, data)


def expected(item):
    return join_expected(item, heading_and_rows)


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "postgres-typed-columns-positive-rejected", reply)
    require(reply["where"] is None and graph(reply) == graph(item), "postgres-typed-columns-provenance-differs",
            {"expected": item, "actual": reply})


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(actual["status"] == "rows", "postgres-typed-columns-native-rejection", {"sql": sql, "actual": actual})
    require(prediction == actual, "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "postgres-typed-columns-fault-native")


def sample_forms(forms, reply, *_):
    tree, fields = graph(reply)
    forms[f"kind/{tree[0]}"] += 1
    for source in leaves(reply["source"]):
        forms[f"only/{source['only']}"] += 1
    addresses = [term for term, _ in fields]
    if len(addresses) != len(set(addresses)):
        forms["repeated"] += 1
    for (tag, occurrence, name), label in fields:
        forms[f"field/{tag}"] += 1
        forms[f"occurrence/{occurrence}"] += 1
        if '"' in label:
            forms["quoted-label"] += 1


def required_forms(*_):
    return {"kind/public", "kind/LEFT", "kind/RIGHT", "kind/CROSS", "only/True", "only/False", "repeated",
            "field/text-slot", "field/slot", "occurrence/0", "occurrence/1", "quoted-label"}


def controls():
    item = finite("pg-17.2", "right-join", "mixed", "only", "forward")
    prediction = expected(item)
    check_observation(item, prediction, "known typed projection")
    altered = {**prediction, "fields": [[prediction["fields"][0][0], "varchar"], prediction["fields"][1]]}
    try:
        check_observation(item, altered, "wrong native field kind")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "postgres-typed-columns-control-signal", failure.detail)
    else:
        raise Failure("postgres-typed-columns-comparator-accepted")
    return {"healthy": prediction, "wrongTextKind": altered}


def faults(_, tag):
    target = "sql-semantics-postgres/src/Ir/SelectSql.php"
    single = finite(tag, "ordinary", "text", "only", "forward")
    return [
        RenderFault("use-left-occurrence-for-right-text", target, "$this->alias($offset + $expression->occurrence)",
                    "$this->alias($offset)", finite(tag, "right-join", "text", "include", "forward"), "meaning-mismatch"),
        RenderFault("swap-text-declaration-slot", target, "$input->columns[$expression->slot]->quoted()",
                    "$input->columns[$expression instanceof TextColumnReference ? 3 : $expression->slot]->quoted()",
                    single, "meaning-mismatch"),
        RenderFault("inherit-a-typed-only-scan", target, "($source->only ? 'ONLY ' : '')", "''", single, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
