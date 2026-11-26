import Solution
import PlacementSpec

open ReindexSpec PlacementSpec

namespace PostgreSqlReindex

private theorem cutStem (stem rest : Text) : cut stem (stem ++ rest) = some rest := by
  induction stem with
  | nil => rfl
  | cons first more step =>
    change (if first = first then cut more (more ++ rest) else none) = _
    rw [ite_eq_left rfl, step]

private theorem takePlacement (verbose : Bool) (tail : Text) :
    stripStem (placementStem verbose ++ tail) = some (verbose, tail) := by
  cases verbose with
  | false =>
    have wrong : cut (placementStem true) (placementStem false ++ tail) = none := rfl
    unfold stripStem
    rw [wrong]
    change (cut (placementStem false) (placementStem false ++ tail)).map (false, ·) = _
    rw [cutStem]
    rfl
  | true =>
    unfold stripStem
    rw [cutStem]

private theorem ordinaryRoundTrip (head : Head) (target : Target) :
    ordinaryStem (reports head) ++ (tailHeader head ++ putTarget target) = encode head target := by
  cases head with
  | index concurrent verbose => cases concurrent <;> cases verbose <;> rfl
  | table concurrent verbose => cases concurrent <;> cases verbose <;> rfl
  | schema concurrent verbose => cases concurrent <;> cases verbose <;> rfl
  | database concurrent verbose => cases concurrent <;> cases verbose <;> rfl
  | system verbose => cases verbose <;> rfl

theorem placementRoundTrip (head : Head) (space : Text) (target : Target) (shape : fits head target) :
    decodePlacement (encodePlacement head space target) = some (head, space, target) := by
  have boundary : (placementSuffix head target).head? ≠ some quote := by
    show some ')' ≠ some quote
    exact of_decide_eq_true rfl
  have name := quotedIdentifier space (placementSuffix head target) boundary
  change (stripStem (placementStem (reports head) ++ (putName space ++ placementSuffix head target)) >>= _) = _
  rw [takePlacement]
  change (getName (putName space ++ placementSuffix head target) >>= _) = _
  rw [name]
  change (identify (ordinaryStem (reports head) ++ (tailHeader head ++ putTarget target)) >>= _) = _
  rw [ordinaryRoundTrip, commandRoundTrip head target shape]
  rfl

end PostgreSqlReindex

#print axioms PostgreSqlReindex.placementRoundTrip
