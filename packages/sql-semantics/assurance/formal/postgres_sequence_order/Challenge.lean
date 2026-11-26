import SignedDecimalSpec

namespace SignedDecimal

theorem decimalOrder
    (leftNegative rightNegative : Bool) (left right : List Digit)
    (leftCanonical : Canonical left) (rightCanonical : Canonical right)
    (leftSign : SignCoherent leftNegative left) (rightSign : SignCoherent rightNegative right) :
    before leftNegative rightNegative left right ↔ signed leftNegative left < signed rightNegative right := by
  sorry

theorem representativeDomain :
    Canonical [0] ∧ Canonical [1] ∧ SignCoherent true [1] ∧
      before true false [1] [0] ∧ signed true [1] < signed false [0] := by
  sorry

end SignedDecimal
