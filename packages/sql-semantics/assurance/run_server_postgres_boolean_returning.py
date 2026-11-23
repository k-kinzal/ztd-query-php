"""Observe exact PostgreSQL Boolean target fields and the whole mutation effect."""

import json
import random
import sys
from itertools import product

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main, observer_request
from run_server_postgres_boolean_fields import DIAGNOSTIC, FIELDS, query
from run_server_postgres_text_returning import observation, operation, sorted_rows
from server_join_cases import compare, slot
from server_truth_model import admits
from sqlite_domain import RenderFault

LABEL = "postgres-boolean-returning"
CONTRACT = "0d94e694277d6daa8921a38d41e177a314e6a0e6a92203ecaae83bf26b634e1b"
CONTRACT_NAME = "postgres-boolean-returning-v1.json"
OBSERVER = "postgres_boolean_returning_observer.php"
TABLE = "ir_bool_changes"
HEADING = ("id", *FIELDS)
KINDS = {"id": ("int4", 23), "enabled": ("bool", 16), "fallback": ("bool", 16)}
INITIAL = [[1, True, False], [1, True, False], [2, False, True], [3, None, False], [None, True, None]]
DEFAULTS = [True, False]
DECLARATIONS = [f"CREATE TABLE public.{TABLE} (id INTEGER, enabled BOOLEAN DEFAULT TRUE, fallback BOOLEAN DEFAULT FALSE)"]
OPERATIONS = ("insert", "insert-query", "update", "delete")


def specifications():
    scope, projection = TOOL / CONTRACT_NAME, TOOL / "server-projection-v1.json"
    require(digest(scope) == CONTRACT and digest(projection) == PROJECTION, "postgres-boolean-returning-contract-mismatch")
    return json.loads(scope.read_text()), {"postgres": json.loads(projection.read_text())["profiles"]["postgres"]}


def prepare(_, db):
    skips = (
        f"DELETE FROM public.{TABLE}; DELETE FROM public.{TABLE}",
        f"DELETE FROM public.{TABLE} RETURNING current_database()",
        f"INSERT INTO public.{TABLE} (id) SELECT id FROM public.{TABLE}",
        f"UPDATE public.{TABLE} SET enabled=TRUE",
        "DELETE FROM public.outside_fixture",
    )
    return {"outside": [db.request({"sql": sql}) for sql in skips],
            "empty": observation(db.request({"sql": f"DELETE FROM public.{TABLE} WHERE FALSE RETURNING *"}))}


def check_preparation(_, environment, prepared):
    same(sorted_rows(INITIAL), sorted_rows(environment["initial"]))
    require(environment["database"][-1] == "pg_catalog, public", "postgres-boolean-returning-profile")
    same([{"status": "outside-observer-scope"}] * 5, prepared["outside"])
    same(expected(mutation("pg-17.2", "delete", [(name, name) for name in HEADING], where=["boolean", False])),
         prepared["empty"])


def reference(name):
    position = HEADING.index(name)
    return slot(position) if name == "id" else ["bool-slot", position, 0]


def integer(value):
    return ["integer", str(value)]


def mutation(tag, form, fields, *, where=None, number=9, inserted=(8, 9), empty=False):
    target = {"schema": "public", "table": TABLE, "columns": list(HEADING), "only": False,
              "columnKinds": [KINDS[name][0] for name in HEADING]}
    base = {"dialect": "postgres", "release": tag, "target": target,
            "returning": [[reference(field), label] for field, label in fields]}
    if form == "insert":
        return {**base, "written": [reference("id")], "rows": [[str(value)] for value in inserted]}
    if form == "insert-query":
        selected = query(tag, None, [[integer(number), "v"]], ["boolean", False] if empty else None)
        return {**base, "written": [reference("id")], "insertQuery": selected}
    if form == "update":
        return {**base, "assignments": [[reference("id"), integer(number)]], "where": where}
    require(form == "delete", "postgres-boolean-returning-unknown-statement", form)
    return {**base, "where": where}


def finite(tag, form, shape, name, mode):
    other = FIELDS[1 - FIELDS.index(name)]
    layout = {
        "single": [(name, "value")], "mixed": [(name, "value"), ("id", "id")],
        "repeated": [(name, "value"), (name, "value")],
        "reordered": [("id", "id"), (name, "value"), (other, "other")],
    }[shape]
    return mutation(tag, form, layout, where=reference(name) if mode == "selected" else None,
                    inserted=(8,) if mode == "selected" else (8, 9), empty=mode == "selected")


def address(term, target):
    role, index = term[:2]
    physical = target["columns"][index]
    kinds = target.get("columnKinds", ["int4"] * len(target["columns"]))
    require((len(term) == 2 or term[2] == 0) and kinds[index] == KINDS[physical][0]
            and role == ("slot" if physical == "id" else "bool-slot"),
            "postgres-boolean-returning-coordinate-kind", [term, target])
    return physical


def predicate(term, target):
    if term is None:
        return None
    role = term[0]
    if role in ("slot", "bool-slot"):
        return role, address(term, target)
    if role == "null-test":
        return role, term[1], predicate(term[2], target)
    if role in ("comparison", "logic"):
        return role, term[1], predicate(term[2], target), predicate(term[3], target)
    require(role in ("boolean", "integer", "null"), "postgres-boolean-returning-unknown-predicate", term)
    return tuple(term)


def graph(item):
    target = item["target"]
    fields = tuple((address(term, target), label) for term, label in item["returning"])
    form = operation(item)
    if form == "insert":
        effects = ([address(term, target) for term in item["written"]], item["rows"])
    elif form == "insert-query":
        select = item["insertQuery"]
        effects = ([address(term, target) for term in item["written"]],
                   [select[key] for key in ("columns", "source", "where", "distinct", "orderBy", "limit")])
    elif form == "update":
        effects = (predicate(item["where"], target),
                   [(address(ref, target), predicate(term, target)) for ref, term in item["assignments"]])
    else:
        effects = (predicate(item["where"], target),)
    return (target["schema"], target["table"], target["only"]), form, effects, fields


def finite_ids(_, spec, _tag):
    f = spec["finite"]
    grid = {(form, layout, field, mode) for form in f["operation"] for layout in f["layout"]
            for field in f["field"] for mode in f["mode"]}
    require(len(grid) == f["identities"], "postgres-boolean-returning-finite-size", len(grid))
    return grid


def finite_id(_, reply, _item):
    _, form, effects, fields = graph(reply)
    columns = tuple(name for name, _ in fields)
    labels = tuple(label for _, label in fields)
    layout = {("value",): "single", ("value", "id"): "mixed",
              ("value", "value"): "repeated", ("id", "value", "other"): "reordered"}[labels]
    field = next(name for name in columns if name in FIELDS)
    if form == "insert":
        selected = len(effects[1]) == 1
    elif form == "insert-query":
        selected = effects[1][2] == ["boolean", False]
    else:
        selected = effects[0] == ("bool-slot", field)
    return form, layout, field, "selected" if selected else "all"


def analyzed(tag):
    name = f"public.{TABLE}"
    yield (f"DELETE FROM {name} AS f WHERE f.enabled RETURNING enabled AS first, f.enabled AS again",
           mutation(tag, "delete", [("enabled", "first"), ("enabled", "again")], where=reference("enabled")))
    yield (f"UPDATE {name} SET id=7 WHERE fallback RETURNING fallback AS bit, id AS assigned",
           mutation(tag, "update", [("fallback", "bit"), ("id", "assigned")], where=reference("fallback"), number=7))
    yield (f"INSERT INTO {name} (id) VALUES (8), (9) RETURNING enabled, fallback",
           mutation(tag, "insert", [(name, name) for name in FIELDS]))
    yield (f"DELETE FROM {name} WHERE FALSE RETURNING *",
           mutation(tag, "delete", [(name, name) for name in HEADING], where=["boolean", False]))
    yield (f"INSERT INTO {name} (id) SELECT 9 AS v RETURNING fallback, id, enabled",
           mutation(tag, "insert-query", [(name, name) for name in ("fallback", "id", "enabled")]))
    yield (f"DELETE FROM {name} WHERE fallback IS NULL RETURNING enabled, fallback",
           mutation(tag, "delete", [(name, name) for name in FIELDS], where=["null-test", False, reference("fallback")]))


def negatives(tag):
    baseline = finite(tag, "delete", "single", "enabled", "all")
    role = "A returned slot must belong to its target."
    for term, reason in (
        (["bool-slot", 0, 0], role), (slot(1), role), (["text-slot", 1, 0], role), (["bool-slot", 3, 0], role),
        (["bool-slot", 1, 1], "A returned field must read the single target occurrence."),
    ):
        yield {**baseline, "returning": [[term, "value"]]}, {"exception": DIAGNOSTIC, "message": reason}


def seeded(tag, seed, count):
    rng = random.Random(seed)
    for index in range(count):
        form = OPERATIONS[index % len(OPERATIONS)]
        fields = [(rng.choice(FIELDS), "bit")]
        fields.extend((rng.choice(HEADING), rng.choice(("bit", "again", '値 " label'))) for _ in range(rng.randint(1, 7)))
        rng.shuffle(fields)
        bit = reference(rng.choice(FIELDS))
        null = ["null-test", bool(rng.getrandbits(1)), reference(rng.choice(FIELDS))]
        where = rng.choice((None, ["boolean", False], bit, null, ["logic", "OR", bit, null],
                            compare("=", reference("id"), integer(rng.randrange(5)))))
        inserted = tuple(rng.randint(-7, 7) for _ in range(rng.randint(1, 5)))
        yield mutation(tag, form, fields, where=where, number=rng.randint(-50, 50), inserted=inserted,
                       empty=bool(rng.getrandbits(1)))


def requests(_, tag, spec):
    fixed = spec["finite"]
    for index, coordinates in enumerate(product(*(fixed[key] for key in ("operation", "layout", "field", "mode")))):
        item = finite(tag, *coordinates)
        yield f"finite/{index}", item, item, None
    positives = list(analyzed(tag))
    require(len(positives) == len(spec["analysis"]), "postgres-boolean-returning-analysis-size")
    for index, (sql, item) in enumerate(positives):
        yield f"analyze/{index}", {"dialect": "postgres", "release": tag, "sql": sql, "declarations": DECLARATIONS}, item, None
    exploration = spec["exploration"]
    for seed in exploration["seeds"]:
        for index, item in enumerate(seeded(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, reason


def expected(item):
    target = item["target"]
    form = operation(item)

    def evaluate(term, row):
        if term[0] == "integer":
            return int(term[1])
        return row[HEADING.index(address(term, target))]

    if form == "insert":
        changed = [[int(row[0]), *DEFAULTS] for row in item["rows"]]
        after = INITIAL + changed
    elif form == "insert-query":
        select = item["insertQuery"]
        changed = [] if select["where"] == ["boolean", False] else [[int(select["columns"][0][0][1]), *DEFAULTS]]
        after = INITIAL + changed
    else:
        after, changed = [], []
        for old in INITIAL:
            if not admits(item["where"], lambda term: evaluate(term, old)):
                after.append(old)
            elif form == "update":
                new = list(old)
                for reference, expr in item["assignments"]:
                    new[HEADING.index(address(reference, target))] = evaluate(expr, old)
                changed.append(new)
                after.append(new)
            else:
                changed.append(old)
    layout = [(HEADING.index(address(ref, target)), label) for ref, label in item["returning"]]
    fields = [[label, *KINDS[HEADING[position]]] for position, label in layout]
    return observation({"status": "mutation", "count": len(changed), "columns": len(fields),
                        "fields": fields, "rows": [[row[position] for position, _ in layout] for row in changed],
                        "before": INITIAL, "after": after, "restored": INITIAL})


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "postgres-boolean-returning-positive-rejected", reply)
    require(graph(item) == graph(reply), "postgres-boolean-returning-graph", {"item": item, "actual": reply})


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual["status"] == "mutation", "postgres-boolean-returning-native-rejection", witness)
    try:
        same(prediction, actual)
    except Failure as error:
        raise Failure(error.signal, witness) from error


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "mutation", "postgres-boolean-returning-fault-native")


def required_forms(*_):
    return {"operation/" + form for form in OPERATIONS} | {
        "field/enabled", "field/fallback", "field/id", "repeated-label", "repeated-field", "reordered",
        "nonempty/True", "nonempty/False", "returned-null", "signed-operand", "where/logic", "where/bool-slot", "where/null-test",
    }


def sample_forms(forms, reply, _engine, item):
    _, form, _, layout = graph(reply)
    forms["operation/" + form] += 1
    columns = [name for name, _ in layout]
    labels = [label for _, label in layout]
    for name in columns:
        forms["field/" + name] += 1
    if len(set(labels)) < len(labels):
        forms["repeated-label"] += 1
    if len(set(columns)) < len(columns):
        forms["repeated-field"] += 1
    if columns != sorted(columns, key=HEADING.index):
        forms["reordered"] += 1
    where = reply.get("where")
    if where is not None:
        forms["where/" + where[0]] += 1
    predicted = expected(item)
    forms[f"nonempty/{bool(predicted['rows'])}"] += 1
    if any(None in row for row in predicted["rows"]):
        forms["returned-null"] += 1
    if form in ("update", "insert", "insert-query"):
        forms["signed-operand"] += 1


def controls():
    item = finite("pg-17.2", "update", "mixed", "enabled", "all")
    prediction = expected(item)
    check_observation(item, prediction, "control")
    mutated = {
        "wrong-oid": {**prediction, "fields": [["value", "bool", 23], prediction["fields"][1]]},
        "wrong-effect": {**prediction, "after": prediction["before"]},
        "lost-restoration": {**prediction, "restored": prediction["after"]},
    }
    receipts = {}
    for name, actual in mutated.items():
        try:
            check_observation(item, actual, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-boolean-returning-control-signal", name)
            receipts[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-boolean-returning-control-accepted", name)
    return receipts


def faults(_, tag):
    base = "sql-semantics-postgres/src/Ir/Mutation/"
    returned = base + "ReturningSql.php"
    emit = "implode(', ', $fields)"
    field = "$target->columns[$field->reference->slot]"
    where = "$this->where === null"
    value = "$sql->expression($assignment->value, $this->target)"
    tests = (
        ("return-other-boolean-field", returned, field, "$target->columns[2]", "delete", "single", "all"),
        ("reverse-boolean-output-layout", returned, emit, "implode(', ', array_reverse($fields))", "insert", "mixed", "all"),
        ("lose-boolean-output-position", returned, emit, "$fields[0]", "insert-query", "mixed", "all"),
        ("erase-boolean-delete-filter", base + "Delete.php", where, "true", "delete", "single", "selected"),
        ("substitute-updated-signed-value", base + "Update.php", value, "'CAST(44 AS pg_catalog.int4)'", "update", "mixed", "all"),
    )
    return [RenderFault(name, path, before, after, finite(tag, form, layout, "enabled", mode), "meaning-mismatch")
            for name, path, before, after, form, layout, mode in tests]


if __name__ == "__main__":
    main(sys.modules[__name__])
