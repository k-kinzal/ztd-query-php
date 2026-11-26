"""Projection coordinates and independent values for native verbatim text modes."""

from itertools import product
import random

from run import require

BACKSLASH_FREE = "NO_BACKSLASH_ESCAPES"
VERBATIM_ANSI = "ANSI_QUOTES,NO_BACKSLASH_ESCAPES"


def literal_coordinates(spec):
    domain = spec["textFinite"]
    coordinates = {("text", mode, value, placement)
                   for mode in domain["modes"] for value in domain["utf8"] for placement in domain["placements"]}
    require(len(coordinates) == domain["identities"], "mysql-verbatim-finite-domain")
    return coordinates


def literal_grid(spec):
    domain = spec["textFinite"]
    return product(*(domain[axis] for axis in domain["order"]))


def seeded(tag, spec, seed, select, local, physical):
    rng = random.Random(seed + 1)
    modes = spec["profiles"] + spec["additionalProfiles"]
    alphabet = "'\"\\\0\t\x1a\na界😀"
    for index in range(spec["exploration"]["directCasesPerSeed"]):
        mode = modes[index % len(modes)]
        width = rng.randint(1, 5)
        depth = (index + index // len(modes)) % 5
        terms = [["slot", rng.randrange(4)] for _ in range(width)]
        terms[rng.randrange(width)] = ["text", rng.choice(alphabet) + "".join(rng.choices(alphabet, k=rng.randrange(12)))]
        tree = select(tag, mode, [[term, f"value_{i}"] for i, term in enumerate(terms)], physical, bool(rng.getrandbits(1)))
        for layer in range(depth):
            slots = rng.sample(range(width), width)
            tree = local(tree, [[slot, f"r{layer}_{i}"] for i, slot in enumerate(slots)])
        yield tree


def analyzed(tag, spec, base, declarations):
    examples = {
        "verbatim-escape": (
            BACKSLASH_FREE, r"SELECT 'a\0\n\x' AS v", [["v", "VAR_STRING"]], [[r"a\0\n\x"]],
            [["text", r"a\0\n\x"]]),
        "verbatim-ansi-escape": (
            VERBATIM_ANSI, r'''SELECT 'a\''b' AS "a""b"''', [['a"b', "VAR_STRING"]], [["a\\'b"]],
            [["text", "a\\'b"]]),
        "verbatim-adjacent": (
            BACKSLASH_FREE, r"SELECT 'a\n' '屋😀' AS v", [["v", "VAR_STRING"]], [[r"a\n" + "屋😀"]],
            [["text", r"a\n" + "屋😀"]]),
        "verbatim-native-nul": (
            VERBATIM_ANSI, "SELECT 'a\0b' AS v", [["v", "VAR_STRING"]], [["a\0b"]],
            [["text", "a\0b"]]),
        "verbatim-double-quote": (
            BACKSLASH_FREE, r'''SELECT "a\n""b" AS v''', [["v", "VAR_STRING"]], [[r'a\n"b']],
            [["text", r'a\n"b']]),
        "verbatim-ansi-column": (
            VERBATIM_ANSI, 'SELECT "peer" AS "p" FROM "mode_rows" ORDER BY 1', [["p", "LONG"]],
            [[None], [17], [17], [71]], [["slot", 0]]),
        "verbatim-set": (
            BACKSLASH_FREE, r"SELECT 'a\n' AS v UNION ALL SELECT 'z''z' AS v", [["v", "VAR_STRING"]],
            [[r"a\n"], ["z'z"]], [["text", r"a\n"], ["text", "z'z"]]),
        "verbatim-unaliased": (
            BACKSLASH_FREE, r"SELECT 'd\n'", [[r"d\n", "VAR_STRING"]], [[r"d\n"]], [["text", r"d\n"]]),
    }
    require(list(examples) == spec["textAnalysis"], "mysql-verbatim-analysis-scope")
    for key, (mode, sql, fields, rows, terms) in examples.items():
        profile = base(tag, mode)
        request = {**profile, "sql": sql, "declarations": [declarations(spec)]}
        yield key, request, {**profile, "example": key, "fields": fields, "rows": rows,
                             "ordered": " ORDER BY " in sql, "textAnalysisTerms": terms}
