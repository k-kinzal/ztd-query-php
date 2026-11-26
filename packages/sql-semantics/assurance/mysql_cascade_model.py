"""The independent staged child check for native InnoDB parent updates."""

from run import TOOL, digest, require

SCOPE = "mysql-native-overlapping-cascade-observation-v1.json"
REVIEW = "ca66718468587be6b290388397054659f0436144205af34fe3ec1007629f15cd"
require(digest(TOOL / SCOPE) == REVIEW, "mysql-native-cascade-overlap-contract")


def permits_staged_row(current, staged, active, writer, child_names, parent_names, parent_rows):
    for symbol, pairs in active:
        if symbol == writer:
            continue
        children = [child_names.index(child) for child, _ in pairs]
        targets = [parent_names.index(parent) for _, parent in pairs]
        facts = [staged[position] for position in children]
        if None in facts or facts == [current[position] for position in children]:
            continue
        if not any(facts == [parent[slot] for slot in targets] for parent in parent_rows):
            return False
    return True
