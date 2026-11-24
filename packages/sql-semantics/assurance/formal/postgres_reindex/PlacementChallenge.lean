import ReindexSpec
import PlacementSpec

open ReindexSpec PlacementSpec

namespace PostgreSqlReindex

theorem quotedIdentifier (name suffix : Text) (boundary : suffix.head? ≠ some quote) :
  getName (putName name ++ suffix) = some (name, suffix) := by sorry

theorem commandRoundTrip (head : Head) (target : Target) (shape : fits head target) :
  identify (encode head target) = some (head, target) := by sorry

theorem placementRoundTrip (head : Head) (space : Text) (target : Target) (shape : fits head target) :
  decodePlacement (encodePlacement head space target) = some (head, space, target) := by sorry

end PostgreSqlReindex
