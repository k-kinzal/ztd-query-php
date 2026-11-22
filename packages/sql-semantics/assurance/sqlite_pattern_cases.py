"""Finite pattern identities, structural exploration and exact ESCAPE outcomes."""

from itertools import product
from random import Random

from sqlite_between_cases import DDL, FIXTURE, col, expected as scalar_observation, role, statement
from sqlite_pattern_semantics import PatternEvaluationError
from sqlite_relational_cases import children, parameter_names


def text(value):
    return ["text", value]


def pattern(kind, subject, matched, negated=False, escape=None):
    return ["pattern", kind, negated, subject, matched, escape]


def finite_cases(domain):
    for subject, negated in product(domain["subjects"], domain["negated"]):
        for like in domain["like"]:
            yield statement("select-unit", [[pattern("LIKE", subject, like, negated)]])
        for like, escape in product(domain["escapedLike"], domain["escapes"]):
            yield statement("select-unit", [[pattern("LIKE", subject, like, negated, escape)]])
        for glob in domain["glob"]:
            yield statement("select-unit", [[pattern("GLOB", subject, glob, negated)]])


def finite_id(reply, domain):
    _, kind, negated, subject, matched, escape = reply["columns"][0][0]
    if kind == "GLOB":
        form, ordinal = 2, domain["glob"].index(matched)
    elif escape is None:
        form, ordinal = 0, domain["like"].index(matched)
    else:
        form = 1
        ordinal = domain["escapedLike"].index(matched) * len(domain["escapes"]) + domain["escapes"].index(escape)
    return domain["subjects"].index(subject), negated, form, ordinal


def finite_ids(domain):
    counts = (len(domain["like"]), len(domain["escapedLike"]) * len(domain["escapes"]), len(domain["glob"]))
    return {(subject, negated, form, ordinal) for subject in range(len(domain["subjects"])) for negated in domain["negated"]
            for form, count in enumerate(counts) for ordinal in range(count)}


def seeded(domain, seed, count):
    rng = Random(seed)
    leaves = [*domain["subjects"], ["integer", "-4"], ["real", "0", "5", None, False], ["blob", "C3A9"],
              ["parameter", "02"], ["parameter", "2"], ["parameter", "7"], text("🦉")]
    for _ in range(count):
        root = rng.choice(("select-unit", "select-table", "values"))
        atoms = leaves + ([col(0), col(1)] if root == "select-table" else [])
        literal_escapes = [*domain["escapes"], ["integer", "5"], ["parameter", "7"]]

        def escape():
            marker = rng.choice(literal_escapes)
            return marker if rng.getrandbits(1) else ["concat", text(""), marker]

        def scalar(depth):
            if depth == 0 or rng.randrange(4) == 0:
                return rng.choice(atoms)
            if rng.randrange(4) == 0:
                return ["concat", scalar(depth - 1), scalar(depth - 1)]
            return decision(depth - 1)

        def decision(depth):
            operation = rng.choice(("LIKE", "GLOB"))
            candidates = domain["glob"] if operation == "GLOB" else domain["like"] + domain["escapedLike"]
            right = rng.choice(candidates) if rng.getrandbits(1) else scalar(depth)
            marker = escape() if operation == "LIKE" and rng.getrandbits(1) else None
            return pattern(operation, scalar(depth), right, bool(rng.getrandbits(1)), marker)

        height = rng.randint(1, 3) if root == "values" else 1
        width = rng.randint(1, 4)
        layout = [[decision(rng.randrange(4)) for _ in range(width)] for _ in range(height)]
        item = statement(root, layout, [rng.choice(("same", "same", "selected", "条件")) for _ in range(width)])
        item["bindings"] = {slot: rng.choice((None, "!", "%", "_", "é")) if slot == "7" else rng.choice((None, 0, "a\0b", "_", "[a-z]"))
                            for slot in item["bindings"]}
        yield item


def boundaries():
    yield statement("select-unit", [[pattern("GLOB", text("]"), text("[]]")), pattern("LIKE", text("Æ"), text("æ")),
                                    pattern("LIKE", text("%"), text("%%"), escape=text("%"))]])
    yield statement("select-unit", [[pattern("LIKE", text("a%c"), text("aé%c"), escape=text("é"))]])
    yield statement("select-unit", [[pattern("LIKE", ["null"], text("a"), escape=text("too long"))]])
    yield statement("select-unit", [[pattern("LIKE", text("a"), ["null"], escape=text(""))]])
    yield statement("select-unit", [[pattern("LIKE", ["parameter", "02"], ["parameter", "2"], escape=["parameter", "7"])]])
    nested = text("a")
    for _ in range(32):
        nested = pattern("LIKE", nested, text("%"))
    yield statement("select-unit", [[nested]])


def analyzed():
    one = pattern("LIKE", text("a"), text("A"))
    two = pattern("GLOB", text("a"), text("A"))
    yield "SELECT 'a' LIKE 'A' AS folded, 'a' GLOB 'A' AS exact", [], statement("select-unit", [[one, two]], ["folded", "exact"])
    yield ("SELECT 'a%c' NOT LIKE 'a!%c' ESCAPE '!' AS escaped",
           [], statement("select-unit", [[pattern("LIKE", text("a%c"), text("a!%c"), True, text("!"))]], ["escaped"]))
    yield ("SELECT n, t LIKE 'A%' AS folded, t GLOB 'A*' AS exact FROM main.ir_range_inputs", [DDL],
           statement("select-table", [[col(0), pattern("LIKE", col(1), text("A%")), pattern("GLOB", col(1), text("A*"))]], ["n", "folded", "exact"]))
    inner = pattern("LIKE", text("a"), text("A"))
    yield ("SELECT '1' LIKE ('a' LIKE 'A') AS nested", [],
           statement("select-unit", [[pattern("LIKE", text("1"), inner)]], ["nested"]))
    yield ("VALUES (X'41' LIKE 'a', 'é' GLOB '?')",
           [], statement("values", [[pattern("LIKE", ["blob", "41"], text("a")), pattern("GLOB", text("é"), text("?"))]]))
    yield ("SELECT 'abc' LIKE 'a%' ESCAPE '' AS invalid", [],
           statement("select-unit", [[pattern("LIKE", text("abc"), text("a%"), escape=text(""))]], ["invalid"]))


def negatives():
    for index in range(3):
        operands = [text("a"), text("a"), text("!")]
        operands[index] = col(0)
        subject, rule, marker = operands
        yield statement("select-unit", [[pattern("LIKE", subject, rule, escape=marker)]]), "rejected", "must belong to the statement source"
    yield (statement("select-unit", [[pattern("GLOB", text("a"), text("a"), escape=text("!"))]]),
           "rejected", "GLOB does not accept an ESCAPE")
    for operator in ("REGEXP", "MATCH"):
        yield {"sql": f"SELECT 'a' {operator} 'b'", "declarations": []}, "rejected", "no resolved SQLite profile"


def forms(reply):
    root = role(reply)
    yield "root/" + root
    expressions = (term for row in reply["rows"] for term in row) if root == "values" else (term for term, _ in reply["columns"])
    for expression in expressions:
        for term in children(expression):
            if term[0] != "pattern":
                continue
            _, kind, negated, subject, rule, marker = term
            yield "kind/" + kind
            yield f"negated/{negated}"
            yield "subject/" + subject[0]
            yield "pattern/" + rule[0]
            yield "escape/" + ("omitted" if marker is None else marker[0])


def expected(item):
    try:
        return {**scalar_observation(item), "evaluationError": None}
    except PatternEvaluationError as error:
        assert role(item) == "select-unit" and len(item["columns"]) == 1
        parameters = parameter_names(item)
        return {"names": [item["columns"][0][1]], "parameters": parameters, "parameterCount": max(map(int, parameters), default=0),
                "rows": [], "readOnly": True, "changes": 0, "evaluationError": {"code": 1, "message": str(error)}}
