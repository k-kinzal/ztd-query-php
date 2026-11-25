"""Independent native UTF-8 labels and completeness grid for local names."""

from itertools import product
from random import Random

import mysql_derived_projection_alias_cases as derived
import mysql_inherited_local_alias_cases as local
import mysql_signed_projection_alias_cases as aliases
from mysql_with_projection_alias_cases import LAYOUTS
from run_server_mysql_legacy_inherited_alias import LEGACY, PLACEMENTS

COMPLETE_PREFIX = "漢" * 85
LABELS = {
    "accent-case": (("  É", "Second"), ("É", "Second"), ("é", "SECOND")),
    "accent-distinct": (("  É", "e"), ("É", "e"), ("é", "E")),
    "normalization-distinct": (("  é", " e\u0301"), ("é", "e\u0301"), ("É", "E\u0301")),
    "case-to-ascii": (("  K", "İd"), ("K", "İd"), ("k", "ID")),
    "no-expanded-match": (("  œ", "  oe"), ("œ", "oe"), ("Œ", "OE")),
    "complete-clipped": ((" " + COMPLETE_PREFIX + "xextra", "  next"), (COMPLETE_PREFIX + "x", "next"), (COMPLETE_PREFIX + "X", "NEXT")),
    "incomplete-clipped-unused": ((" " + "漢" * 90, COMPLETE_PREFIX, "  tail"), (None, COMPLETE_PREFIX, "tail"), (None, COMPLETE_PREFIX, "TAIL")),
}
CLIP = "incomplete-clipped-unused"


def grid(contract, tag):
    fixed = contract["finite"]
    placements = (*fixed["standardPlacements"], *(() if tag in LEGACY else fixed["ctePlacements"]))
    yield from product(fixed["aliasShapes"], placements, fixed["layout"])


def independent_ids(tag):
    placements = PLACEMENTS if tag in LEGACY else local.PLACEMENTS
    return {(label, place, order) for place in placements for order in LAYOUTS for label in LABELS}


def layout(shape, place, exposed, order):
    inputs = [[side, slot] for side, labels in enumerate(exposed)
              for slot, label in enumerate(labels) if label is not None]
    if place == "join" and shape != CLIP:
        return [list(pair) for pair in derived.JOIN_LAYOUTS[order]]
    if place == "nested":
        inputs = [[0, slot] for slot in range(len(inputs))]
    if order == "reverse":
        return list(reversed(inputs))
    if order == "repeat":
        return [inputs[-1], *inputs[:1], inputs[-1], *inputs[1:-1]]
    return inputs


def finite(tag, shape, place, order):
    requested, visible, references = LABELS[shape]
    if place == "join":
        left = ("00011", "-7", "12")
        right = ("29", "51", "-16")
        options = ((requested, visible, references, left), (requested[::-1], visible[::-1], references[::-1], right))
    else:
        options = ((requested, visible, references, ("00011", "-2147483649", "57")),)
    sources = [aliases.case(tag, *zip(atoms[:len(names)], names, [local.STYLE] * len(names), strict=True))
               for names, _, _, atoms in options]
    exposed = [list(names) for _, names, _, _ in options]
    written = [list(names) for _, _, names, _ in options]
    return {"tag": tag, "placement": place, "sources": sources, "exposed": exposed, "referenceNames": written,
            "order": layout(shape, place, exposed, order), "natural": order == "repeat"}


def seeded(tag, seed, count, pairs):
    rng = Random(seed)
    placements = PLACEMENTS if tag in LEGACY else local.PLACEMENTS
    places = sorted(placements)
    rng.shuffle(places)
    for step in range(count):
        place = places[step % len(places)]
        atom = derived.NARROW if place == "join" else (*derived.NARROW, *derived.LARGE)
        widths = [rng.randint(2, 4) for _ in range(2 if place == "join" else 1)]
        sources, exposed, written = [], [], []
        for side, width in enumerate(widths):
            group, names, uses = [], [], []
            for slot in range(width):
                lower, upper = rng.choice(pairs)
                base = f"{upper}a_{slot}"
                names.append(base)
                uses.append(f"{lower}A_{slot}")
                group.append([rng.choice(atom), rng.choice((" ", "  ", "\x7f", "")) + base, rng.choice(aliases.STYLES)])
            sources.append(aliases.case(tag, *group))
            exposed.append(names)
            written.append(uses)
        order = [[side, slot] for side, width in enumerate(widths) for slot in range(width)]
        rng.shuffle(order)
        if step % 2 == 0:
            order.append(list(order[0]))
        yield {"tag": tag, "placement": place, "sources": sources, "exposed": exposed, "referenceNames": written,
               "order": order, "natural": bool(step % 3)}
