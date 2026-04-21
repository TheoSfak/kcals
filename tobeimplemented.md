# KCALS — Features To Be Implemented

Each feature gets its own minor version bump (0.9.1, 0.9.2, …).
Delete the entry from this file after the feature is fully implemented & committed.

---

## v0.9.4 — Adaptive TDEE Recalibration
Every 4 weeks, compare predicted vs actual weight loss.
- If actual loss < 80% of predicted: recalculate TDEE downward and notify user.
- If actual loss > 120% of predicted: recalculate TDEE upward (metabolism higher than estimated).
- Show a "Your TDEE was recalibrated" card on the dashboard after each recalibration.

## v0.9.5 — Hormetic Recharge Day
Once per week (configurable day, default Wednesday), automatically add +150 kcal above TDEE.
- Fill that day's plan with complex-carb-heavy foods.
- Show a "Recharge Day" badge on the plan card for that day.
- Explain the science to the user (leptin / metabolic adaptation prevention).

## v0.9.6 — Recovery Mode
If stress_level ≥ 8 for 2 consecutive check-ins:
- Automatically enter "Recovery Mode": deficit drops to 5% of TDEE for the next generated plan.
- Plan shifts to comfort-food-friendly options.
- Show a Recovery Mode banner on dashboard with explanation.
- Auto-exit Recovery Mode when stress_level drops below 6.
