"""Compare typed PostgreSQL RETURNING rows and physical table effects."""

from itertools import product
import json
import random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main, observer_request
from run_server_postgres_typed_columns import DIAGNOSTICS
from server_join_cases import compare, slot
from server_truth_model import admits
from sqlite_domain import RenderFault

LABEL = "postgres-text-returning"
CONTRACT = "252345f6bc001056f4545abdd72a0d5def31d059bea70d29638a042e0bada831"
OBSERVER = "postgres_text_returning_observer.php"
TABLE = "ir_text_changes"
HEADING = ("id", "label", "note")
COLUMN_TYPES = ("int4", "text", "text")
STATEMENTS = ("insert", "insert-query", "update", "delete")
INITIAL = [[1, "old 値", "first"], [1, "old 値", "first"], [2, None, "second"], [3, "", None],
           [None, "unkeyed", "other"]]
DEFAULTS = ("added α", "new memo")
DECLARATIONS = [
    f"CREATE TABLE public.{TABLE} (id integer, label pg_catalog.text DEFAULT 'added α', "
    "note pg_catalog.text DEFAULT 'new memo')",
]


def specifications():
    path, profile = TOOL / "postgres-text-returning-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "postgres-text-returning-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def sorted_rows(rows):
    return sorted(rows, key=lambda row: json.dumps(row, ensure_ascii=False))


def observation(reply):
    return {key: sorted_rows(value) if key in {"rows", "before", "after", "restored", "inputBefore", "inputAfter", "inputRestored"} else value
            for key, value in reply.items()}


def prepare(_, db):
    invalid = (
        f"DELETE FROM public.{TABLE}; DELETE FROM public.{TABLE}",
        f"DELETE FROM public.{TABLE} RETURNING current_database()",
        f"INSERT INTO public.{TABLE} (id) SELECT 1 FROM public.{TABLE}",
        f"INSERT INTO public.{TABLE} (label) VALUES (12345678901)",
        "UPDATE public.other_table SET id = 1",
    )
    return {"outside": [db.request({"sql": sql}) for sql in invalid],
            "inactive": observation(db.request({"sql": f"DELETE FROM public.{TABLE} WHERE FALSE RETURNING label"}))}


def check_preparation(_, environment, prepared):
    same(sorted_rows(INITIAL), sorted_rows(environment["initial"]))
    require(environment["int4TextCastOverrides"] == 0, "postgres-native-int4-text-cast-profile", environment)
    same([{"status": "outside-observer-scope"}] * 5, prepared["outside"])
    same(observation({"status": "mutation", "count": 0, "columns": 1, "fields": [["label", "text"]],
                      "rows": [], "before": INITIAL, "after": INITIAL, "restored": INITIAL}), prepared["inactive"])


def reference(name):
    ordinal = HEADING.index(name)
    return slot(ordinal) if name == "id" else ["text-slot", ordinal, 0]


def int4(number):
    return ["integer", str(number)]


def selected(name="label", nonnull=False):
    return ["null-test", nonnull, reference(name)]


def mutation(tag, operation, fields, *, where=None, value=9, values=(8, 9), query_empty=False):
    target = {"schema": "public", "table": TABLE, "columns": list(HEADING), "columnKinds": list(COLUMN_TYPES), "only": False}
    base = {"dialect": "postgres", "release": tag, "target": target,
            "returning": [[reference(name), alias] for name, alias in fields]}
    if operation == "insert":
        return {**base, "written": [reference("id")], "rows": [[str(number)] for number in values]}
    if operation == "insert-query":
        return {**base, "written": [reference("id")], "insertQuery": {
            "dialect": "postgres", "release": tag, "columns": [[int4(value), "v"]], "source": None,
            "where": ["boolean", False] if query_empty else None, "distinct": False, "limit": None, "orderBy": [],
        }}
    if operation == "update":
        return {**base, "assignments": [[reference("id"), int4(value)]], "where": where}
    require(operation == "delete", "postgres-text-returning-unknown-operation", operation)
    return {**base, "where": where}


def finite(tag, operation, layout, effect):
    forms = {"text": [("label", "text")], "mixed": [("note", "memo"), ("id", "id")],
             "repeated": [("label", "value"), ("label", "again")]}
    return mutation(tag, operation, forms[layout], where=selected() if effect == "selected" else None,
                    values=(8,) if effect == "selected" else (8, 9), query_empty=effect == "selected")


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    coordinates = set(product(b["operation"], b["layout"], b["effect"]))
    require(len(coordinates) == b["identities"], "postgres-text-returning-finite-size", coordinates)
    return coordinates


def operation(item):
    if "rows" in item:
        return "insert"
    if "insertQuery" in item:
        return "insert-query"
    if "assignments" in item:
        return "update"
    require("where" in item, "postgres-text-returning-operation-missing", item)
    return "delete"


def address(term, layout):
    role, index = term[:2]
    physical = layout["columns"][index]
    kinds = layout.get("columnKinds", ["int4"] * len(layout["columns"]))
    require(role in ("slot", "text-slot") and (len(term) == 2 or term[2] == 0)
            and kinds[index] == COLUMN_TYPES[HEADING.index(physical)]
            and (kinds[index] == "text") == (role == "text-slot"),
            "postgres-text-returning-coordinate-kind", [term, layout])
    return physical


def condition(term, layout):
    if term is None:
        return None
    if term[0] in ("slot", "text-slot"):
        return term[0], address(term, layout)
    if term[0] == "null-test":
        return term[0], term[1], condition(term[2], layout)
    if term[0] == "comparison":
        return term[0], term[1], condition(term[2], layout), condition(term[3], layout)
    if term[0] == "text-comparison":
        return term[0], term[1], term[2], condition(term[3], layout), condition(term[4], layout)
    if term[0] == "logic":
        return term[0], term[1], condition(term[2], layout), condition(term[3], layout)
    require(term[0] in ("boolean", "integer", "text", "null"), "postgres-text-returning-unknown-predicate", term)
    return tuple(term)


def graph(item):
    target = item["target"]
    returns = tuple((address(ref, target), name) for ref, name in item["returning"])
    form = operation(item)
    if form == "insert":
        shape = [item["rows"], [address(ref, target) for ref in item["written"]]]
    elif form == "insert-query":
        query = item["insertQuery"]
        shape = [[query[key] for key in ("columns", "source", "where", "distinct", "orderBy", "limit")],
                 [address(ref, target) for ref in item["written"]]]
    elif form == "update":
        shape = [condition(item["where"], target),
                 [(address(ref, target), condition(term, target)) for ref, term in item["assignments"]]]
    else:
        shape = [condition(item["where"], target)]
    return target["schema"], target["table"], target["only"], form, shape, returns


def finite_id(_, reply, _item):
    _, _, _, form, shape, layout = graph(reply)
    names = tuple(name for name, _ in layout)
    kind = {("label",): "text", ("note", "id"): "mixed", ("label", "label"): "repeated"}[names]
    if form == "insert":
        all_rows = len(shape[0]) == 2
    elif form == "insert-query":
        all_rows = shape[0][2] is None
    else:
        all_rows = shape[0] is None
    return form, kind, "all" if all_rows else "selected"


def analysis_request(tag, sql):
    return {"dialect": "postgres", "release": tag, "declarations": DECLARATIONS, "sql": sql}


def positives(tag):
    name = f"public.{TABLE}"
    yield (f"DELETE FROM {name} WHERE label IS NULL RETURNING label AS v, {TABLE}.label AS again",
           mutation(tag, "delete", [("label", "v"), ("label", "again")], where=selected()))
    yield (f"UPDATE {name} SET id = 9 WHERE id = 2 RETURNING label AS v, id AS current_id",
           mutation(tag, "update", [("label", "v"), ("id", "current_id")], where=compare("=", reference("id"), int4(2))))
    yield (f"INSERT INTO {name} (id) VALUES (8), (9) RETURNING label, note",
           mutation(tag, "insert", [("label", "label"), ("note", "note")]))
    yield (f"DELETE FROM {name} WHERE FALSE RETURNING *",
           mutation(tag, "delete", [(field, field) for field in HEADING], where=["boolean", False]))
    yield (f"INSERT INTO {name} (id) SELECT 9 AS v RETURNING label, id",
           mutation(tag, "insert-query", [("label", "label"), ("id", "id")]))


def negatives(tag):
    def bad(term):
        item = finite(tag, "delete", "text", "all")
        item["returning"][0][0] = term
        return item

    yield bad(["text-slot", 0, 0]), "A returned slot must belong to its target."
    yield bad(slot(1)), "A returned slot must belong to its target."
    yield bad(["text-slot", 1, 1]), "A returned field must read the single target occurrence."
    yield bad(["text-slot", 3, 0]), "A returned slot must belong to its target."


def exploratory(tag, seed, count):
    rng = random.Random(seed)
    for _ in range(count):
        form = rng.choice(STATEMENTS)
        fields = [("label", rng.choice(("first", "same", '値 " quoted')))]
        fields += [(rng.choice(HEADING), rng.choice(("first", "same", '値 " quoted'))) for _ in range(rng.randrange(6))]
        rng.shuffle(fields)
        where = rng.choice((None, ["boolean", False], selected("label", bool(rng.getrandbits(1))),
                            selected("note", bool(rng.getrandbits(1))), compare("=", reference("id"), int4(1))))
        yield mutation(tag, form, fields, where=where, value=rng.randint(-8, 8),
                       values=tuple(rng.randint(-8, 8) for _ in range(rng.randint(1, 4))),
                       query_empty=bool(rng.getrandbits(1)))


def requests(_, tag, spec):
    b = spec["finite"]
    for index, point in enumerate(product(b["operation"], b["layout"], b["effect"])):
        item = finite(tag, *point)
        yield f"finite/{index}", item, item, None
    for index, (sql, item) in enumerate(positives(tag)):
        yield f"analyze/{index}", analysis_request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(exploratory(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, message) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": message}


def expected(item):
    form = operation(item)
    layout = item["target"]
    initial = INITIAL
    after, affected = [], []

    def evaluate(term, row):
        if term[0] == "integer":
            return int(term[1])
        if term[0] == "text":
            return term[1]
        if term[0] == "null":
            return None
        return row[HEADING.index(address(term, layout))]

    if form == "insert":
        affected = [[int(row[0]), *DEFAULTS] for row in item["rows"]]
        after = initial + affected
    elif form == "insert-query":
        query = item["insertQuery"]
        affected = [] if query["where"] == ["boolean", False] else [[int(query["columns"][0][0][1]), *DEFAULTS]]
        after = initial + affected
    else:
        for row in initial:
            if not admits(item["where"], lambda term: evaluate(term, row)):
                after.append(row)
                continue
            if form == "delete":
                affected.append(row)
            else:
                changed = list(row)
                for ref, term in item["assignments"]:
                    index = HEADING.index(address(ref, layout))
                    value = evaluate(term, row)
                    changed[index] = str(value) if COLUMN_TYPES[index] == "text" and isinstance(value, int) else value
                affected.append(changed)
                after.append(changed)
    positions = [HEADING.index(address(ref, layout)) for ref, _ in item["returning"]]
    return observation({
        "status": "mutation", "count": len(affected), "columns": len(positions),
        "fields": [[name, COLUMN_TYPES[i]] for i, (_, name) in zip(positions, item["returning"])],
        "rows": [[row[i] for i in positions] for row in affected],
        "before": initial, "after": after, "restored": initial,
    })


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "postgres-text-returning-positive-rejected", reply)
    require(graph(item) == graph(reply), "postgres-text-returning-graph-differs", {"expected": item, "actual": reply})


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(actual["status"] == "mutation", "postgres-text-returning-native-rejection", {"sql": sql, "actual": actual})
    require(prediction == actual, "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "mutation", "postgres-text-returning-fault-native")


def sample_forms(forms, reply, *_):
    _, _, _, form, _, layout = graph(reply)
    forms["operation/" + form] += 1
    fields = [field for field, _ in layout]
    labels = [label for _, label in layout]
    if len(set(labels)) < len(labels):
        forms["repeated-output-name"] += 1
    if len(set(fields)) < len(fields):
        forms["repeated-slot"] += 1
    if fields != sorted(fields, key=HEADING.index):
        forms["reordered"] += 1
    prediction = expected(reply)
    forms[f"nonempty/{bool(prediction['rows'])}"] += 1
    if any(value is None for row in prediction["rows"] for value in row):
        forms["returned-null"] += 1
    if any(DEFAULTS[0] in row for row in prediction["rows"]):
        forms["returned-default"] += 1


def required_forms(*_):
    return {"operation/" + form for form in STATEMENTS} | {
        "repeated-output-name", "repeated-slot", "reordered", "nonempty/True", "nonempty/False",
        "returned-null", "returned-default",
    }


def controls():
    item = finite("pg-17.2", "update", "mixed", "all")
    prediction = expected(item)
    check_observation(item, prediction, "known mutation and native text")
    wrong_kind = {**prediction, "fields": [["memo", "int4"], ["id", "int4"]]}
    wrong_effect = {**prediction, "after": prediction["before"]}
    for name, altered in (("wrong native kind", wrong_kind), ("physical update missing", wrong_effect)):
        try:
            check_observation(item, altered, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "postgres-text-returning-control-signal", failure.detail)
        else:
            raise Failure("postgres-text-returning-comparator-accepted", name)
    return {"healthy": prediction, "wrongKind": wrong_kind, "wrongEffect": wrong_effect}


def faults(_, tag):
    source = "sql-semantics-postgres/src/Ir/Mutation/ReturningSql.php"
    clause = "$target->columns[$field->reference->slot]"
    return [
        RenderFault("return-other-text-field", source, clause, "$target->columns[2]",
                    finite(tag, "delete", "text", "all"), "meaning-mismatch"),
        RenderFault("erase-text-returning-clause", source, "return $fields === [] ? '' : ' RETURNING ' . implode(', ', $fields);",
                    "return '';", finite(tag, "insert", "text", "all"), "meaning-mismatch"),
        RenderFault("substitute-integer-returning-slot", source, clause, "$target->columns[0]",
                    finite(tag, "update", "text", "selected"), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
