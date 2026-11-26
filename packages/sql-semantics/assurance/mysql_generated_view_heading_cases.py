"""Independent assignment of generated persistent MySQL view headings."""

from itertools import product
from random import Random

from mysql_create_view_output_name_cases import LEGACY
import mysql_create_view_projection_alias_cases as view
import mysql_signed_projection_alias_cases as aliases
from run_server_mysql_create_view import TARGET, case as view_case, query

FORMS = {
    "one": "1",
    "negative-one": "-1",
    "unary-plus": "(+7)",
    "padded-63": "0" * 62 + "1",
    "padded-64": "0" * 63 + "1",
    "padded-65": "0" * 64 + "1",
    "padded-257": "0" * 256 + "1",
}
PATTERNS = ("same", "same-three", "fixed-earlier", "fixed-later", "counter-before", "counter-after",
            "other-release-prefix", "generated-position-collision", "empty-requested", "empty-with-duplicate")
PLACEMENTS = ("written", "rotate")
EMPTY_REQUEST = " \t\x7f"


def token(expression):
    return expression[2:-1] if expression.startswith("(+") else expression


def heading_prefix(tag):
    return "My_exp_" if tag in LEGACY else "Name_exp_"


def variants(tag, atom):
    original = token(atom)
    prefix = heading_prefix(tag)
    other = "Name_exp_" if tag in LEGACY else "My_exp_"
    binding = original if len(original) <= 64 else "Name_exp_1"
    civil = (atom, None)
    clash = (59, binding)
    duplicate_prefix = (61, (prefix + original)[:64])
    return {
        "same": [civil, civil],
        "same-three": [civil, civil, civil],
        "fixed-earlier": [clash, civil],
        "fixed-later": [civil, clash],
        "counter-before": [duplicate_prefix, civil, civil, civil],
        "counter-after": [civil, civil, duplicate_prefix, civil],
        "other-release-prefix": [(61, (other + original)[:64]), civil, civil, civil],
        "generated-position-collision": [civil, (83, "Name_exp_1"), civil],
        "empty-requested": [(67, EMPTY_REQUEST), civil],
        "empty-with-duplicate": [(67, EMPTY_REQUEST), civil, civil],
    }


def finite(tag, pattern, spelling, placement):
    selected = [*variants(tag, FORMS[spelling])[pattern], (71, "anchor")]
    if placement == "rotate":
        selected = selected[-1:] + selected[:-1]
    return {"tag": tag, "operands": [[str(token), alias] for token, alias in selected]}


def grid(contract):
    finite_spec = contract["finite"]
    yield from product(finite_spec["patterns"], finite_spec["literalForms"], finite_spec["placements"])


def independent_ids():
    return {(pattern, form, placement) for placement in PLACEMENTS for form in FORMS for pattern in PATTERNS}


def assigned(item):
    release = item["tag"]
    prefix = heading_prefix(release)
    requested = [token(expr) if alias is None else aliases.effective(alias) for expr, alias in item["operands"]]
    automatic = [alias is None for _, alias in item["operands"]]
    installed = [f"Name_exp_{position + 1}" if automatic[position] and len(name) > 64 else name
                 for position, name in enumerate(requested)]
    if len({name.lower() for name in installed}) == len(installed):
        return installed
    for current in range(len(installed)):
        for earlier in range(current):
            if installed[current].lower() != installed[earlier].lower():
                continue
            changed = current if automatic[current] else earlier if automatic[earlier] else None
            if changed is None:
                raise ValueError("fixed view name collision")
            occupied = {installed[index].lower() for index in range(current + 1) if index != changed}
            for attempt in range(current + 2):
                counter = "" if attempt == 0 else f"{attempt}_"
                candidate = (prefix + counter + requested[changed])[:191 if release in LEGACY else 64]
                if candidate.lower() not in occupied:
                    installed[changed] = candidate
                    break
            else:
                raise ValueError("view name assignment exhausted")
    return installed


def expression(raw):
    spelling = token(raw)
    if len(spelling.lstrip("-")) > 10:
        return ["projected-integer", spelling]
    return aliases.wire_expression(spelling)


def structure(item, observed=False):
    positions = []
    for raw, label in item["operands"]:
        intrinsic = token(raw)
        if label is None:
            heading = ["intrinsic-integer-name", intrinsic] if len(intrinsic) > 256 else intrinsic
        else:
            heading = aliases.wire_label(label, observed)
        positions.append([expression(raw), heading])
    return view_case(item["tag"], query(None, positions), assigned(item))


def standalone_structure(item):
    return {"dialect": "mysql", "release": item["tag"], **structure(item)["createView"]["query"]}


def standalone_expected(item):
    rows = aliases.expected(aliases.case(item["tag"], *((raw, alias, aliases.STYLES[0])
                            for raw, alias in item["operands"])))
    for record in rows.values():
        record["fields"] = [[name, kind, length, flags] for name, kind, flags, length in record["fields"]]
    return rows


def command(item):
    projection = ", ".join(raw if label is None else raw + " AS " + aliases.quote(label, aliases.STYLES[0])
                           for raw, label in item["operands"])
    start = "SELECT " if item.get("standalone") else "CREATE VIEW " + TARGET + " AS SELECT " 
    return start + projection


def expected(item):
    fields = assigned(item)
    # An inferred name has no explicit alias assignment to report.
    body = [[token(raw), "stable_operand" if alias is None else alias, aliases.STYLES[0]]
            for raw, alias in item["operands"]]
    baseline = view.expected({"tag": item["tag"], "columns": body, "fields": fields})
    legacy = item["tag"] in LEGACY
    catalog = []
    for position, (name, (raw, _)) in enumerate(zip(fields, item["operands"], strict=True), start=1):
        heading = name[:64] if legacy else name
        spelling = token(raw)
        value = int(spelling)
        # Modern catalog creation uses the literal token's width. A later
        # read opens the stored numeric expression with its normalized value.
        wide_spelling = not legacy and len(spelling.lstrip("-")) > 10
        kind = "int" if not wide_spelling and -(2**31) <= value < 2**31 else "bigint"
        catalog.append([heading.encode().hex().upper(), position, kind, "NO"])
    for observation in baseline.values():
        observation["catalogColumns"] = catalog
    return baseline


def maximum(tag, width):
    return {"tag": tag, "operands": [[str(index), f"field_{index}"] for index in range(width)]}


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        spelling = rng.choice(tuple(FORMS.values()))
        prefix = heading_prefix(tag)
        variant = rng.choice(PATTERNS)
        group = variants(tag, spelling)[variant]
        if index % 3 == 1:
            group.append((rng.choice((17, 23, 991)), EMPTY_REQUEST if all(label != EMPTY_REQUEST for _, label in group) else "other"))
        if index % 4 == 1:
            group.append((rng.choice((419, 421)), prefix + "unrelated"))
        group.append((rng.choice((101, 129, 193)), "anchor"))
        rng.shuffle(group)
        yield {"tag": tag, "operands": [[str(raw), alias] for raw, alias in group]}
