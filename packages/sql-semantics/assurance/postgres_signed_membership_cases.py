"""Independent signed membership plans and three-valued observations."""

from itertools import product
from random import Random

from postgres_drop_index_cases import qualified, quoted
from server_result_cases import result_window

SAMPLE = "sem_ir_membership"
PEER = "sem_ir_membership_peer"
SHADOW_SCHEMA = "sem_ir_membership_decoys"
FIELDS = ("a", "b")
KINDS = ("int4", "int8")
OIDS = {"int4": 23, "int8": 20, "bool": 16}
ROWS = {
    SAMPLE: [(2, 2147483648), (2, 2), (-2, -9223372036854775808), (10, 9223372036854775807),
             (None, 2), (2, None), (None, None), (2, 2), (2147483647, 2147483648), (-2147483648, -2147483649)],
    PEER: [(1, 2147483648), (2, 2), (3, None), (4, 9223372036854775807), (5, 2), (None, None)],
}
SOURCES = ("unit", "resolved-table")
PLACEMENTS = ("projection", "filter")
POLARITY = (False, True)
LENGTHS = (1, 2, 3)
FIXED = (("integer", "2"), ("wide-integer", "2147483648"), ("null",))
RELATION = (("slot", 0), ("wide-slot", 1, 0))
TERM = "signed-membership"
NULL = ["null"]
WIDE_NULL = ["wide-null"]
UPDATED = 317


def table(name=SAMPLE):
    return {"schema": "public", "table": name, "columns": list(FIELDS), "only": False, "columnKinds": list(KINDS)}


def declarations():
    return [f"CREATE TABLE {qualified('public', name)} (a INTEGER, b BIGINT)" for name in ROWS]


def pool(source):
    return FIXED if source == "unit" else (*FIXED, *RELATION)


def coordinates():
    return {(source, placement, negated, subject, alternatives)
            for source, placement, negated in product(SOURCES, PLACEMENTS, POLARITY)
            for size in LENGTHS for subject in pool(source) for alternatives in product(pool(source), repeat=size)}


def defined_coordinates(contract):
    terms = contract["finite"]
    unit = tuple(map(tuple, terms["unitOperands"]))
    tags = {"column": "slot", "wide-column": "wide-slot"}
    extra = tuple((tags[kind], *([slot] if kind == "column" else [slot, occurrence]))
                  for kind, slot, occurrence in terms["tableExtraOperands"])
    for source, role, negated in product(terms["sources"], terms["placements"], terms["negated"]):
        values = unit if source == "unit" else (*unit, *extra)
        for size in terms["lengths"]:
            for subject, *alternatives in product(values, repeat=size + 1):
                yield source, role, negated, subject, tuple(alternatives)


def membership(negated, subject, choices):
    return [TERM, negated, list(subject), [list(choice) for choice in choices]]


def operand(kind, value):
    return [kind, str(value)]


def scenario(tag, source, placement, term, *, output=None, order=None, wrapper=None, shadow=False):
    selected = None if source == "unit" else table()
    update = [[["slot", 0], ["integer", str(UPDATED)]]]
    result_fields = [[["slot", 0], "first"], [["wide-slot", 1, 0], "second"]]
    selection = result_fields if output is None else output
    if placement == "join":
        selected = {"kind": "LEFT", "left": table(), "right": table(PEER), "on": term}
        selection = [[["slot", 0], "first"], [["wide-slot", 1, 1], "second"]]
    return {"tag": tag, "sourceForm": source, "source": selected, "placement": placement, "term": term,
            "columns": [[term, "observed"]] if placement == "projection" and output is None else selection,
            "orderBy": [] if order is None else order, "wrapper": wrapper, "shadow": shadow, "returning": result_fields,
            "assignments": update}


def finite(tag, identity):
    source, placement, negated, subject, choices = identity
    output = None if placement == "projection" else [[["integer", "41"], "kept"]] if source == "unit" else None
    return scenario(tag, source, placement, membership(negated, subject, choices), output=output)


def quoted_field(term, source):
    slot, *rest = term[1:]
    occurrence = rest[0] if rest else 0
    if source and "kind" in source:
        return qualified(("lhs", "rhs")[occurrence], FIELDS[slot])
    return quoted(FIELDS[slot])


def raw_operand(term, source):
    kind = term[0]
    if kind in ("slot", "wide-slot"):
        return quoted_field(term, source)
    if kind in ("integer", "wide-integer"):
        return term[1]
    if kind == "null":
        return "NULL"
    if kind == "wide-null":
        return "CAST(NULL AS pg_catalog.int8)"
    if kind == "boolean":
        return "TRUE" if term[1] else "FALSE"
    if kind == TERM:
        _, negated, subject, alternatives = term
        values = ", ".join(raw_operand(choice, source) for choice in alternatives)
        return f"({raw_operand(subject, source)} {'NOT IN' if negated else 'IN'} ({values}))"
    if kind == "truth-test":
        return f"({raw_operand(term[2], source)} {term[1]})"
    if kind == "null-test":
        return f"({raw_operand(term[2], source)} IS {'NOT ' if term[1] else ''}NULL)"
    if kind == "int4-case":
        branches = " ".join(f"WHEN {raw_operand(test, source)} THEN {raw_operand(value, source)}"
                            for test, value in term[1])
        return f"(CASE {branches} ELSE {raw_operand(term[2], source)} END)"
    if kind == "logic":
        _, operator, left, right = term
        primary = raw_operand(left, source)
        return f"(NOT {primary})" if right is None else f"({primary} {operator} {raw_operand(right, source)})"
    if kind == "operation":
        return f"({raw_operand(term[2], source)} {term[1]} {raw_operand(term[3], source)})"
    raise ValueError(term)


def tablename(source):
    return qualified(source["schema"], source["table"])


def sql(item):
    source, placement = item["source"], item["placement"]
    def fields(expressions):
        return ", ".join(raw_operand(term, source) + " AS " + quoted(name) for term, name in expressions)
    condition = "" if placement not in ("filter", "update", "delete") else " WHERE " + raw_operand(item["term"], source)
    if placement == "update":
        return f"UPDATE {tablename(source)} SET a = {UPDATED}" + condition + " RETURNING " + fields(item["returning"])
    if placement == "delete":
        return f"DELETE FROM {tablename(source)}" + condition + " RETURNING " + fields(item["returning"])
    relation = ""
    if placement == "join":
        relation = f' FROM {tablename(source["left"])} lhs LEFT JOIN {tablename(source["right"])} rhs ON ' + raw_operand(item["term"], source)
    elif source is not None:
        relation = " FROM " + tablename(source)
    order = ""
    if item["orderBy"]:
        order = " ORDER BY " + ", ".join(f"{slot} {'DESC' if desc else 'ASC'} NULLS {'FIRST' if nulls else 'LAST'}"
                                        for slot, desc, nulls in item["orderBy"])
    return "SELECT " + fields(item["columns"]) + relation + condition + order


def analysis(item):
    path = [SHADOW_SCHEMA, "pg_catalog", "public"] if item["shadow"] else ["pg_catalog", "public"]
    return {"dialect": "postgres", "release": item["tag"], "sql": sql(item), "declarations": declarations(), "schemaPath": path}


def full_heading_request(item):
    source, role, term = item["source"], item["placement"], item["term"]
    prefix = {"dialect": "postgres", "release": item["tag"]}
    if role in ("update", "delete"):
        return {**prefix, "kind": role, "target": source, "where": term, "returning": item["returning"],
                **({"assignments": item["assignments"]} if role == "update" else {})}
    return {**prefix, "columns": item["columns"], "source": source, "where": term if role == "filter" else None,
            "distinct": False, "orderBy": item["orderBy"], "limit": None}


def allocated(term, layouts):
    kind = term[0]
    if kind in ("slot", "wide-slot"):
        slot, *rest = term[1:]
        occurrence = rest[0] if rest else 0
        used = layouts[occurrence]
        if slot not in used:
            used.append(slot)
        local = used.index(slot)
        return [kind, local, *rest]
    if kind == TERM:
        return [kind, term[1], allocated(term[2], layouts), [allocated(child, layouts) for child in term[3]]]
    if kind in ("truth-test", "null-test"):
        return [kind, term[1], allocated(term[2], layouts)]
    if kind == "logic":
        return [kind, term[1], allocated(term[2], layouts), None if term[3] is None else allocated(term[3], layouts)]
    if kind == "int4-case":
        branches = [[allocated(condition, layouts), allocated(value, layouts)] for condition, value in term[1]]
        return [kind, branches, allocated(term[2], layouts)]
    if kind in ("integer", "wide-integer", "null", "wide-null", "boolean"):
        return term
    raise ValueError(term)


def consumed(source, slots):
    heading = {**source, "columns": [source["columns"][index] for index in slots]}
    kinds = [heading["columnKinds"][index] for index in slots]
    del heading["columnKinds"]
    if any(kind != "int4" for kind in kinds):
        heading["columnKinds"] = kinds
    return heading


def direct(item):
    request = full_heading_request(item)
    layout = [[], []]
    where = request.get("where")
    if where is not None:
        request["where"] = allocated(where, layout)
    if "target" in request:
        if "assignments" in request:
            request["assignments"] = [[allocated(slot, layout), allocated(value, layout)]
                                      for slot, value in request["assignments"]]
        request["returning"] = [[allocated(term, layout), name] for term, name in request["returning"]]
        request["target"] = consumed(request["target"], layout[0])
        return request
    request["columns"] = [[allocated(term, layout), name] for term, name in request["columns"]]
    source = request["source"]
    if source and "kind" in source:
        on = allocated(source["on"], layout)
        request["source"] = {**source, "left": consumed(source["left"], layout[0]),
                             "right": consumed(source["right"], layout[1]), "on": on}
    elif source is not None:
        request["source"] = consumed(source, layout[0])
    return request


def physical_operand(term, source):
    if term[0] in ("slot", "wide-slot"):
        name = source["columns"][term[1]]
        return (term[0], FIELDS.index(name), *term[2:])
    return tuple(term)


def graph(item):
    value = direct(item)
    return {key: spec for key, spec in value.items() if key not in ("dialect", "release", "kind")}


def native_kind(term):
    kind = term[0]
    if kind in (TERM, "truth-test", "null-test", "boolean", "logic"):
        return "bool"
    if kind in ("integer", "int4-case") or kind == "slot":
        return "int4"
    if kind in ("wide-integer", "wide-slot"):
        return "int8"
    raise ValueError(term)


def negated(value):
    return None if value is None else not value


def evaluate(term, row):
    kind = term[0]
    if kind in ("slot", "wide-slot"):
        slot, *rest = term[1:]
        return row[rest[0] if rest else 0][slot]
    if kind in ("integer", "wide-integer"):
        return int(term[1])
    if kind in ("null", "wide-null"):
        return None
    if kind == "boolean":
        return term[1]
    if kind == TERM:
        subject = evaluate(term[2], row)
        alternatives = [evaluate(choice, row) for choice in term[3]]
        matched = subject is not None and subject in alternatives
        result = True if matched else None if subject is None or None in alternatives else False
        return negated(result) if term[1] else result
    if kind == "truth-test":
        target = {"IS TRUE": True, "IS FALSE": False, "IS UNKNOWN": None}[term[1]]
        return evaluate(term[2], row) is target
    if kind == "null-test":
        return (evaluate(term[2], row) is None) != term[1]
    if kind == "int4-case":
        for condition, value in term[1]:
            if evaluate(condition, row) is True:
                return evaluate(value, row)
        return evaluate(term[2], row)
    if kind == "logic":
        left = evaluate(term[2], row)
        if term[1] == "NOT":
            return negated(left)
        right = evaluate(term[3], row)
        decisive = term[1] == "OR"
        return decisive if decisive in (left, right) else None if None in (left, right) else not decisive
    raise ValueError(term)


def project(terms, inputs, order):
    rows = [[evaluate(term, row) for term, _ in terms] for row in inputs]
    fields = [[name, kind, OIDS[kind]] for term, name in terms for kind in [native_kind(term)]]
    return result_window(fields, rows, order, None)


def expected(item):
    role, term = item["placement"], item["term"]
    inputs = [()] if item["source"] is None else [(row,) for row in ROWS[SAMPLE]]
    if role == "join":
        joined = []
        for (left,) in inputs:
            matches = [(left, right) for right in ROWS[PEER] if evaluate(term, (left, right)) is True]
            joined.extend(matches if matches else [(left, (None, None))])
        inputs = joined
    if role in ("update", "delete"):
        changed, after = [], []
        for row in inputs:
            if evaluate(term, row) is True:
                if role == "update":
                    row = ((UPDATED, row[0][1]),)
                    after.append(list(row[0]))
                changed.append(row)
            else:
                after.append(list(row[0]))
        return project(item["returning"], changed, []), after
    if role == "filter":
        inputs = [row for row in inputs if evaluate(term, row) is True]
    return project(item["columns"], inputs, item["orderBy"]), None


def generated(tag, seed, budget):
    rng = Random(seed)
    placements = (*PLACEMENTS, "join", "update", "delete")
    pure = (*FIXED, WIDE_NULL, operand("integer", -2147483648), operand("integer", 2147483647),
            operand("wide-integer", -9223372036854775808), operand("wide-integer", 9223372036854775807))
    for i in range(budget):
        placement = placements[i % len(placements)]
        source = "unit" if placement == "projection" and i % 2 == 0 else "resolved-table"
        leaves = list(pure)
        if source != "unit":
            leaves.extend(RELATION)
        if placement == "join":
            leaves.extend((("slot", 0, 1), ("wide-slot", 1, 1)))
        options = rng.choices(leaves, k=1 + rng.randrange(12))
        term = membership(bool(rng.getrandbits(1)), rng.choice(leaves), options)
        wrapper, output, order = None, None, None
        if placement == "projection":
            selector = (i // len(placements)) % 4
            if selector == 1:
                wrapper, expression = "case", ["int4-case", [[term, ["integer", "19"]]], ["integer", "41"]]
            elif selector == 2:
                wrapper, expression = "truth", ["truth-test", "IS UNKNOWN", term]
            elif selector == 3:
                wrapper, expression = "null", ["null-test", True, term]
            else:
                expression = term
            output = [[expression, rng.choice(("answer", '決定 "sign"'))]]
        elif placement == "filter" and i % 2 == 0:
            other = membership(False, rng.choice(leaves), rng.choices(leaves, k=2))
            wrapper, term = "logical", ["logic", rng.choice(("AND", "OR")), term, other]
            order = [[1, bool(rng.getrandbits(1)), rng.choice((True, False))]]
        yield scenario(tag, source, placement, term, output=output, order=order, wrapper=wrapper)
