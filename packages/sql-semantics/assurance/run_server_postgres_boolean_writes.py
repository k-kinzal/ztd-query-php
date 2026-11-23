"""Compare positional Boolean writes with a separate evaluator for old and new rows."""

import json
import random
import sys
from itertools import product

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from run_server_postgres_boolean_fields import DIAGNOSTIC, query
from run_server_postgres_boolean_returning import (
    DECLARATIONS as TARGET_DECLARATIONS, DEFAULTS, FIELDS, HEADING, INITIAL, KINDS, OBSERVER, TABLE,
    address, integer, mutation, observation, operation, reference, sorted_rows,
)
from server_join_cases import compare, slot
from server_truth_model import admits, predicate_value
from sqlite_domain import RenderFault

LABEL = "postgres-boolean-writes"
CONTRACT_NAME = "postgres-boolean-writes-v1.json"
CONTRACT = "da56df05098ff809b75570477e4d71d1dea17ee75b2ce6ca7202a4416b91567e"
INPUT = "ir_bool_inputs"
INPUT_HEADING = ("fallback", "id", "enabled")
INPUT_ROWS = [[True, 2, False], [True, 2, False], [False, 3, None], [None, None, True], [False, 4, False]]
DECLARATIONS = [*TARGET_DECLARATIONS, f"CREATE TABLE public.{INPUT} (fallback BOOLEAN, id INTEGER, enabled BOOLEAN)"]
POSITIONS = {"forward": FIELDS, "reversed": FIELDS[::-1]}
ATOMS = {"true": ["boolean", True], "false": ["boolean", False], "null": None}
RETURNING = [("id", "id"), ("enabled", "active"), ("fallback", "previous")]
OPERATIONS = ("insert", "insert-query", "update")
TARGET_SQL = f"public.{TABLE}"
INPUT_SQL = f"public.{INPUT}"
NATIVE_NEGATIVES = (
    f"INSERT INTO {TARGET_SQL} (enabled) SELECT DISTINCT NULL AS v RETURNING enabled",
    f"INSERT INTO {TARGET_SQL} (fallback) SELECT CAST('true' AS pg_catalog.text) AS v RETURNING fallback",
)


def specifications():
    scope, projection = TOOL / CONTRACT_NAME, TOOL / "server-projection-v1.json"
    require(digest(scope) == CONTRACT and digest(projection) == PROJECTION, "postgres-boolean-writes-contract-mismatch")
    return json.loads(scope.read_text()), {"postgres": json.loads(projection.read_text())["profiles"]["postgres"]}


def observer_request(_, sql):
    return {"sql": sql, "observeWrites": True}


def prepare(_, db):
    wrong = (f"UPDATE {INPUT_SQL} SET enabled = TRUE", f"INSERT INTO {TARGET_SQL} (enabled) SELECT current_database()",
             f"UPDATE {TARGET_SQL} SET enabled = TRUE; DELETE FROM {TARGET_SQL}")
    return {"outside": [db.request(observer_request(None, sql)) for sql in wrong],
            "nativeErrors": [observation(db.request(observer_request(None, sql))) for sql in NATIVE_NEGATIVES]}


def check_preparation(_, environment, prepared):
    same(sorted_rows(INITIAL), sorted_rows(environment["initial"]))
    same(sorted_rows(INPUT_ROWS), sorted_rows(environment["input"]))
    require(environment["database"][-1] == "pg_catalog, public", "postgres-boolean-writes-profile")
    same([{"status": "outside-observer-scope"}] * 3, prepared["outside"])
    result = observation({"status": "db-error", "state": "42804", "before": INITIAL, "restored": INITIAL,
                          "inputBefore": INPUT_ROWS, "inputRestored": INPUT_ROWS})
    same([result, result], prepared["nativeErrors"])


def input_table():
    return {"schema": "public", "table": INPUT, "only": False, "columns": list(INPUT_HEADING),
            "columnKinds": [KINDS[name][0] for name in INPUT_HEADING]}


def input_field(name):
    ordinal = INPUT_HEADING.index(name)
    return slot(ordinal) if name == "id" else ["bool-slot", ordinal, 0]


def literal(form):
    return ["null"] if form == "null" else ATOMS[form]


def values(tag, names, supplied, returning=RETURNING):
    item = mutation(tag, "insert", returning)
    return {**item, "written": [reference(name) for name in names], "rows": supplied}


def insert_query(tag, names, source, output, where=None, distinct=False, returning=RETURNING):
    item = mutation(tag, "insert-query", returning)
    return {**item, "written": [reference(name) for name in names],
            "insertQuery": query(tag, source, output, where, distinct)}


def update(tag, fields, where=None, returning=RETURNING):
    item = mutation(tag, "update", returning, where=where)
    return {**item, "assignments": [[reference(name), term] for name, term in fields]}


def finite_values(tag, order, mode, first, second):
    row = [ATOMS[first], ATOMS[second]]
    return values(tag, POSITIONS[order], [row] if mode == "single" else [row, row])


def finite_query(tag, order, distinct, filter_mode):
    output = [[input_field("fallback"), "a"], [input_field("enabled"), "b"]]
    where = input_field("fallback") if filter_mode == "selected" else None
    return insert_query(tag, POSITIONS[order], input_table(), output, where, distinct)


def finite_update(tag, target, value, filter_mode):
    other = FIELDS[1 - FIELDS.index(target)]
    fields = [(target, reference(other) if value in ("old-other", "swap") else literal(value))]
    if value == "swap":
        fields.append((other, reference(target)))
    return update(tag, fields, where=reference("enabled") if filter_mode == "selected" else None)


def finite_ids(_, spec, _tag):
    limits = spec["finite"]
    fields = ("true", "false", "null")
    value_ids = {("insert", order, mode, a, b) for order in limits["values"]["fieldOrder"] for mode in limits["values"]["mode"]
                 for a in fields for b in fields}
    query_ids = {("insert-query", order, distinct, mode) for order in limits["query"]["fieldOrder"]
                 for distinct in limits["query"]["distinct"] for mode in limits["query"]["filter"]}
    update_ids = {("update", target, role, mode) for target in limits["update"]["target"] for role in limits["update"]["value"]
                  for mode in limits["update"]["filter"]}
    for key, identities in (("values", value_ids), ("query", query_ids), ("update", update_ids)):
        require(len(identities) == limits[key]["identities"], "postgres-boolean-writes-subdomain-size", key)
    union = value_ids | query_ids | update_ids
    require(len(union) == limits["identities"], "postgres-boolean-writes-domain-size", len(union))
    return union


def resolved_expression(term, layout):
    if term is None:
        return None
    role = term[0]
    if role in ("slot", "bool-slot"):
        return role, address(term, layout)
    if role in ("null-test", "truth-test", "builtin-cast"):
        return role, term[1], resolved_expression(term[2], layout)
    if role in ("logic", "comparison"):
        return role, term[1], resolved_expression(term[2], layout), resolved_expression(term[3], layout)
    require(role in ("boolean", "integer", "null"), "postgres-boolean-writes-unknown-expression", term)
    return tuple(term)


def graph(item):
    target = item["target"]
    returned = tuple((address(term, target), name) for term, name in item["returning"])
    form = operation(item)
    if form == "update":
        writes = tuple((address(term, target), resolved_expression(rhs, target)) for term, rhs in item["assignments"])
        effect = writes, resolved_expression(item["where"], target)
    else:
        written = tuple(address(term, target) for term in item["written"])
        if form == "insert":
            effect = written, item["rows"]
        else:
            select = item["insertQuery"]
            source = select["source"]
            physical = None if source is None else (source["schema"], source["table"], source["only"])
            outputs = tuple((resolved_expression(term, source), label) for term, label in select["columns"])
            where = resolved_expression(select["where"], source)
            effect = written, physical, outputs, where, select["distinct"], select["orderBy"], select["limit"]
    return (target["schema"], target["table"], target["only"]), form, effect, returned


def atom_key(atom):
    return {None: "null", ("boolean", True): "true", ("boolean", False): "false"}[None if atom is None else tuple(atom)]


def finite_id(_, reply, _item):
    _, form, effect, _ = graph(reply)
    order = {columns: name for name, columns in POSITIONS.items()}
    if form == "insert":
        written, cells = effect
        require(len(cells) in (1, 2) and all(row == cells[0] for row in cells), "postgres-boolean-writes-finite-rows", cells)
        return form, order[written], "single" if len(cells) == 1 else "repeated", *map(atom_key, cells[0])
    if form == "insert-query":
        written, _, _, where, distinct, _, _ = effect
        require(where in (None, ("bool-slot", "fallback")), "postgres-boolean-writes-finite-query-filter", where)
        return form, order[written], distinct, "all" if where is None else "selected"
    writes, where = effect
    target, term = writes[0]
    role = "swap" if len(writes) == 2 else "old-other" if term[0] == "bool-slot" else atom_key(None if term[0] == "null" else term)
    require(where in (None, ("bool-slot", "enabled")), "postgres-boolean-writes-finite-update-filter", where)
    return form, target, role, "all" if where is None else "selected"


def analyzed(tag):
    t, s = TARGET_SQL, INPUT_SQL
    yield (f"INSERT INTO {t} (id,enabled,fallback) VALUES (1,TRUE,NULL),(2,FALSE,FALSE) RETURNING enabled AS active, id",
           values(tag, HEADING, [["1", ATOMS["true"], None], ["2", ATOMS["false"], ATOMS["false"]]], RETURNING[1::-1]))
    yield (f"INSERT INTO {t} (fallback,enabled) VALUES (TRUE,FALSE),(TRUE,FALSE) RETURNING enabled AS active, fallback AS previous",
           values(tag, FIELDS[::-1], [[ATOMS["true"], ATOMS["false"]]] * 2, RETURNING[1:]))
    yield (f"UPDATE {t} SET enabled=fallback, fallback=enabled WHERE id=2 RETURNING id, enabled AS active, fallback AS previous",
           update(tag, [("enabled", reference("fallback")), ("fallback", reference("enabled"))],
                  compare("=", reference("id"), integer(2))))
    yield (f"INSERT INTO {t} (fallback,id,enabled) SELECT enabled,id,fallback FROM {s} RETURNING id, enabled AS active, fallback AS previous",
           insert_query(tag, INPUT_HEADING, input_table(), [[input_field(name), name] for name in HEADING[1::-1]+("fallback",)]))
    yield (f"INSERT INTO {t} (enabled,fallback) SELECT DISTINCT enabled AS p,fallback AS q FROM {s} RETURNING id, enabled AS active, fallback AS previous",
           insert_query(tag, FIELDS, input_table(), [[input_field("enabled"), "p"], [input_field("fallback"), "q"]], distinct=True))
    yield (f"INSERT INTO {t} (enabled) SELECT NULL AS v RETURNING enabled AS active",
           insert_query(tag, ("enabled",), None, [[["null"], "v"]], returning=RETURNING[1:2]))
    yield (f"INSERT INTO {t} (enabled) SELECT DISTINCT CAST(NULL AS pg_catalog.bool) AS v RETURNING enabled AS active",
           insert_query(tag, ("enabled",), None, [[["builtin-cast", "bool", ["null"]], "v"]], distinct=True, returning=RETURNING[1:2]))
    yield (f"INSERT INTO {t} (enabled) SELECT fallback IS UNKNOWN AS decision FROM {s} RETURNING enabled AS active",
           insert_query(tag, ("enabled",), input_table(), [[["truth-test", "IS UNKNOWN", input_field("fallback")], "decision"]],
                        returning=RETURNING[1:2]))


def negative_item(statement, boundary):
    return statement, {"exception": DIAGNOSTIC, "message": boundary}


def analysis_request(tag, sql):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": DECLARATIONS}


def negatives(tag):
    cell = "An inserted cell must match its target native kind."
    assignment = "An UPDATE assignment value must match its target native kind."
    query_type = "A query INSERT Boolean target requires a Boolean result or NULL resolved before DISTINCT."
    yield negative_item(values(tag, ("id",), [[ATOMS["true"]]]), cell)
    yield negative_item(values(tag, ("enabled",), [["1"]]), cell)
    yield negative_item(values(tag, ("enabled",), [[["text", "true"]]]), cell)
    yield negative_item(update(tag, [("id", ATOMS["true"])]), assignment)
    yield negative_item(update(tag, [("enabled", reference("id"))]), assignment)
    yield negative_item(update(tag, [("enabled", ATOMS["true"]), ("enabled", ATOMS["false"])]), "cannot repeat a target slot")
    yield negative_item(insert_query(tag, ("enabled",), input_table(), [[["bool-slot", 1, 0], "v"]]),
                        "A Boolean slot must belong to the SELECT source")
    yield negative_item(insert_query(tag, ("enabled",), None, [[integer(1), "v"]]), query_type)
    yield negative_item(insert_query(tag, ("enabled",), None, [[["null"], "v"]], distinct=True), query_type)
    yield negative_item(analysis_request(tag, NATIVE_NEGATIVES[0]), "cannot assign an untyped NULL to a non-text column")
    yield negative_item(analysis_request(tag, NATIVE_NEGATIVES[1]), query_type)


def seeded(tag, seed, count):
    rng = random.Random(seed)

    def where_for(field):
        bit = field(rng.choice(FIELDS))
        null_test = ["null-test", bool(rng.getrandbits(1)), field(rng.choice(FIELDS))]
        return rng.choice((None, bit, null_test, ["logic", "OR", bit, null_test], ["boolean", False]))

    for index in range(count):
        names = rng.sample(list(FIELDS), rng.randint(1, 2))
        returned = [(rng.choice(HEADING), rng.choice(("value", '値 " c', "value")))
                    for _ in range(rng.randint(1, 5))]
        form = OPERATIONS[index % len(OPERATIONS)]
        if form == "insert":
            rows = [[rng.choice(tuple(ATOMS.values())) for _ in names] for _ in range(rng.randint(1, 5))]
            yield values(tag, names, rows, returned)
        elif form == "insert-query":
            output = [[rng.choice((input_field(rng.choice(FIELDS)), ATOMS["true"], ATOMS["false"],
                                   ["builtin-cast", "bool", ["null"]], ["null-test", False, input_field(rng.choice(FIELDS))])),
                       rng.choice(("v", '値 " field'))] for _ in names]
            yield insert_query(tag, names, input_table(), output, where_for(input_field), bool(rng.getrandbits(1)), returned)
        else:
            fields = [(name, rng.choice((reference(rng.choice(FIELDS)), *map(literal, ATOMS)))) for name in names]
            if len(fields) == 2 and index % 2:
                fields = [(names[0], reference(names[1])), (names[1], reference(names[0]))]
            yield update(tag, fields, where_for(reference), returned)


def requests(_, tag, spec):
    f = spec["finite"]
    v, q, u = (f[k] for k in ("values", "query", "update"))
    exhaustive = [
        *(finite_values(tag, *args) for args in product(v["fieldOrder"], v["mode"], v["operands"], v["operands"])),
        *(finite_query(tag, *args) for args in product(q["fieldOrder"], q["distinct"], q["filter"])),
        *(finite_update(tag, *args) for args in product(u["target"], u["value"], u["filter"])),
    ]
    for index, item in enumerate(exhaustive):
        yield f"finite/{index}", item, item, None
    positives = list(analyzed(tag))
    require(len(positives) == len(spec["analysis"]), "postgres-boolean-writes-analysis-size")
    for index, (sql, item) in enumerate(positives):
        yield f"analyze/{index}", analysis_request(tag, sql), item, None
    explore = spec["exploration"]
    for seed in explore["seeds"]:
        for index, item in enumerate(seeded(tag, seed, explore["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, rejection) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, rejection


def evaluate(term, layout, heading, row):
    role = term[0]
    if role in ("slot", "bool-slot"):
        return row[heading.index(address(term, layout))]
    if role == "integer":
        return int(term[1])
    if role in ("boolean", "null", "null-test", "truth-test", "comparison", "logic"):
        return predicate_value(term, lambda child: evaluate(child, layout, heading, row))
    require(role == "builtin-cast" and term[1] == "bool" and term[2][0] in ("null", "boolean"),
            "postgres-boolean-writes-oracle-cast", term)
    return evaluate(term[2], layout, heading, row)


def expected(item):
    target = item["target"]
    form = operation(item)
    changed = []
    if form == "update":
        after = []
        for old in INITIAL:
            if not admits(item["where"], lambda term: evaluate(term, target, HEADING, old)):
                after.append(old)
                continue
            new = list(old)
            for term, operand in item["assignments"]:
                new[HEADING.index(address(term, target))] = evaluate(operand, target, HEADING, old)
            changed.append(new)
            after.append(new)
    else:
        written = [address(term, target) for term in item["written"]]
        if form == "insert":
            produced = [[None if x is None else evaluate(x, None, (), ()) if isinstance(x, list) else int(x)
                         for x in row] for row in item["rows"]]
        else:
            query = item["insertQuery"]
            source = query["source"]
            data = [()] if source is None else INPUT_ROWS
            produced = [tuple(evaluate(expr, source, INPUT_HEADING, source_row) for expr, _ in query["columns"])
                        for source_row in data
                        if admits(query["where"], lambda term: evaluate(term, source, INPUT_HEADING, source_row))]
            if query["distinct"]:
                produced = list(dict.fromkeys(produced))
        for row in produced:
            fields = dict(zip(written, row))
            changed.append([fields.get("id"), *(fields.get(name, default) for name, default in zip(FIELDS, DEFAULTS))])
        after = INITIAL + changed
    layout = [(address(term, target), name) for term, name in item["returning"]]
    return observation({"status": "mutation", "count": len(changed), "columns": len(layout),
                        "fields": [[label, *KINDS[name]] for name, label in layout],
                        "rows": [[row[HEADING.index(name)] for name, _ in layout] for row in changed],
                        "before": INITIAL, "after": after, "restored": INITIAL,
                        "inputBefore": INPUT_ROWS, "inputAfter": INPUT_ROWS, "inputRestored": INPUT_ROWS})


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "postgres-boolean-writes-positive-rejected", reply)
    require(graph(item) == graph(reply), "postgres-boolean-writes-graph", {"expected": item, "actual": reply})


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual["status"] == "mutation", "postgres-boolean-writes-native-rejection", witness)
    try:
        same(prediction, actual)
    except Failure as error:
        raise Failure(error.signal, witness) from error


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "mutation", "postgres-boolean-writes-fault-native")


def required_forms(*_):
    return {"operation/" + form for form in OPERATIONS} | {
        "field/enabled", "field/fallback", "omitted-field", "order/reversed", "count/many",
        "value/null", "value/true", "value/false", "update/old-field", "update/swap", "query/distinct",
        "query/all", "query/filter", "plan/empty", "repeated-label",
    }


def sample_forms(forms, reply, _, item):
    _, form, effect, returned = graph(reply)
    written = tuple(pair[0] for pair in effect[0]) if form == "update" else effect[0]
    forms["operation/" + form] += 1
    for name in written:
        forms["field/" + name] += 1
    if len(written) == 1:
        forms["omitted-field"] += 1
    if written == FIELDS[::-1]:
        forms["order/reversed"] += 1
    if len(set(label for _, label in returned)) < len(returned):
        forms["repeated-label"] += 1
    if form == "insert":
        for row in effect[1]:
            for operand in row:
                forms["value/" + atom_key(operand)] += 1
    elif form == "insert-query":
        forms["query/" + ("distinct" if effect[4] else "all")] += 1
        if effect[3] is not None:
            forms["query/filter"] += 1
    else:
        assignments = dict(effect[0])
        if any(operand[0] == "bool-slot" for operand in assignments.values()):
            forms["update/old-field"] += 1
        if all(assignments.get(name) == ("bool-slot", other) for name, other in zip(FIELDS, FIELDS[::-1])):
            forms["update/swap"] += 1
    count = expected(item)["count"]
    if count > 1:
        forms["count/many"] += 1
    elif count == 0:
        forms["plan/empty"] += 1


def controls():
    item = finite_update("pg-17.2", "enabled", "swap", "all")
    prediction = expected(item)
    check_observation(item, prediction, "control")
    records = {}
    for name, actual in (
        ("unchanged-target", {**prediction, "after": prediction["before"]}),
        ("source-modification", {**prediction, "inputAfter": []}),
        ("wrong-field-kind", {**prediction, "fields": [*prediction["fields"][:2], ["previous", "int4", 23]]}),
    ):
        try:
            check_observation(item, actual, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-boolean-writes-control-signal", error.detail)
            records[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-boolean-writes-control-accepted", name)
    return records


def faults(_, tag):
    ir = "sql-semantics-postgres/src/Ir/"
    values_example = finite_values(tag, "forward", "repeated", "true", "false")
    update_example = finite_update(tag, "enabled", "swap", "all")
    query_example = finite_query(tag, "forward", True, "all")
    native_slot = "foreach ($this->written as $column)"
    written_value = "$sql->expression($assignment->value, $this->target)"
    return [
        RenderFault("reverse-boolean-destination", ir + "Mutation/Insert/Destination.php", native_slot,
                    "foreach (array_reverse($this->written) as $column)", values_example, "meaning-mismatch"),
        RenderFault("flip-boolean-value", ir + "Mutation/InsertCell.php", "$this->value->value ? 'TRUE' : 'FALSE'",
                    "'FALSE'", values_example, "meaning-mismatch"),
        RenderFault("drop-boolean-assignment", ir + "Mutation/Update.php", "$parts[] = $column . ' = '",
                    "if ($parts !== []) { continue; }\n            $parts[] = $column . ' = '", update_example, "meaning-mismatch"),
        RenderFault("use-new-boolean-value-in-swap", ir + "Mutation/Update.php", written_value,
                    "$sql->expression($this->assignments[0]->value, $this->target)", update_example, "meaning-mismatch"),
        RenderFault("drop-boolean-query-distinct", ir + "SelectSql.php", "$select->distinct ? 'SELECT DISTINCT ' : 'SELECT '",
                    "'SELECT '", query_example, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
