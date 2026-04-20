<?php
// ============================================================
// KCALS – Smart Meal Builder
// Assembles meals from individual foods (foods table).
// Picks foods by type/slot/diet/season, calculates gram portions
// to hit a calorie target, and generates bilingual meal names.
// ============================================================

class MealBuilder
{
    private PDO    $db;
    private string $diet;    // standard|vegan|vegetarian|gf|vegan_gf|keto|paleo
    private int    $month;
    private array  $dislikes; // lowercase strings to avoid

    public function __construct(PDO $db, string $diet, int $month, array $dislikes = [])
    {
        $this->db       = $db;
        $this->diet     = strtolower(trim($diet));
        $this->month    = $month;
        $this->dislikes = array_map('mb_strtolower', $dislikes);
    }

    // ──────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────

    /**
     * Build a single meal.
     *
     * @param string $slot        breakfast|lunch|dinner|snack
     * @param int    $targetKcal  calorie budget for this meal
     * @param array  $usedWeek    food IDs used this week (for variety)
     * @param array  $usedToday   food IDs used in earlier meals today
     * @return array  meal: slot, name_el, name_en, calories, protein_g,
     *                     carbs_g, fat_g, prep_minutes, components[]
     */
    public function buildMeal(
        string $slot,
        int $targetKcal,
        array $usedWeek  = [],
        array $usedToday = []
    ): array {
        $components = match ($slot) {
            'breakfast' => $this->buildBreakfast($targetKcal, $usedWeek),
            'lunch',
            'dinner'    => $this->buildMainMeal($slot, $targetKcal, $usedWeek, $usedToday),
            'snack'     => $this->buildSnack($targetKcal, $usedWeek),
            default     => [],
        };

        // Aggregate totals
        $totalCal = $totalP = $totalC = $totalF = 0;
        foreach ($components as $c) {
            $totalCal += $c['cal'];
            $totalP   += $c['protein_g'];
            $totalC   += $c['carbs_g'];
            $totalF   += $c['fat_g'];
        }

        [$nameEl, $nameEn] = $this->buildName($slot, $components);

        $prep = 5;
        if (!empty($components)) {
            $prep = max(array_column($components, 'prep_minutes'));
        }
        if ($slot === 'lunch' || $slot === 'dinner') {
            $prep = max($prep, 15);
        }

        return [
            'slot'         => $slot,
            'name_el'      => $nameEl,
            'name_en'      => $nameEn,
            'calories'     => (int) round($totalCal),
            'protein_g'    => (int) round($totalP),
            'carbs_g'      => (int) round($totalC),
            'fat_g'        => (int) round($totalF),
            'prep_minutes' => (int) $prep,
            'components'   => $components,
        ];
    }

    // ──────────────────────────────────────────────────────────
    // Meal builders
    // ──────────────────────────────────────────────────────────

    private function buildBreakfast(int $target, array $usedWeek): array
    {
        $isKeto = $this->diet === 'keto';

        if ($isKeto) {
            // Keto breakfast: protein/dairy + fat + keto-ok fruit (berries)
            $excl    = $usedWeek;
            $protein = $this->pick('protein|dairy', 'breakfast', $excl);
            if ($protein) $excl[] = (int) $protein['food_id'];
            $fat     = $this->pick('fat', 'breakfast', $excl);
            if ($fat)     $excl[] = (int) $fat['food_id'];
            $fruit   = $this->pick('fruit', 'breakfast', $excl);

            $components = [];
            if ($protein) $components[] = $this->calc($protein, $target * 0.50);
            if ($fat)     $components[] = $this->calc($fat,     $target * 0.33);
            if ($fruit)   $components[] = $this->calc($fruit,   $target * 0.17);
            return $components ?: $this->fallback('breakfast', $target);
        }

        // Standard / vegan / vegetarian / paleo / gf
        $excl    = $usedWeek;
        $carb    = $this->pick('carb',          'breakfast', $excl);
        if ($carb)    $excl[] = (int) $carb['food_id'];
        $protein = $this->pick('protein|dairy', 'breakfast', $excl);
        if ($protein) $excl[] = (int) $protein['food_id'];
        $fruit   = $this->pick('fruit',         'breakfast', $excl);
        if ($fruit)   $excl[] = (int) $fruit['food_id'];

        $used = 0.0;
        $components = [];

        if ($carb) {
            $kcal = $target * 0.44;
            $components[] = $this->calc($carb, $kcal);
            $used += $kcal;
        }
        if ($protein) {
            $kcal = $target * 0.28;
            $components[] = $this->calc($protein, $kcal);
            $used += $kcal;
        }
        if ($fruit) {
            $kcal = $target * 0.24;
            $components[] = $this->calc($fruit, $kcal);
            $used += $kcal;
        }

        // Add fat with any significant remaining budget (happens when carb=null e.g. paleo)
        $remaining = $target - $used;
        if ($remaining > 50) {
            $fat = $this->pick('fat', 'breakfast', $excl);
            if ($fat) $components[] = $this->calc($fat, $remaining);
        }

        return $components ?: $this->fallback('breakfast', $target);
    }

    private function buildMainMeal(
        string $slot,
        int $target,
        array $usedWeek,
        array $usedToday
    ): array {
        $isKeto = $this->diet === 'keto';

        // Protein: try to differ from today's meals; fall back wider if needed
        $protein = $this->pick('protein', $slot, array_merge($usedWeek, $usedToday));
        if (!$protein) $protein = $this->pick('protein', $slot, $usedToday);
        if (!$protein) $protein = $this->pick('protein', $slot, []);

        // Carb (skip for keto)
        $excl = $usedWeek;
        if ($protein) $excl[] = (int) $protein['food_id'];

        $carb = null;
        if (!$isKeto) {
            $carb = $this->pick('carb', $slot, $excl);
            if ($carb) $excl[] = (int) $carb['food_id'];
        }

        // Two different vegetables
        $veg1 = $this->pick('vegetable', $slot, $excl);
        if ($veg1) $excl[] = (int) $veg1['food_id'];
        $veg2 = $this->pick('vegetable', $slot, $excl);

        if (!$protein) return $this->fallback($slot, $target);

        // Fixed 10 g olive oil as cooking fat (88 kcal)
        $oilComp   = $this->oilComponent();
        $remaining = $target - 88;

        $components = [];

        if ($isKeto) {
            // No carb: shift budget to protein + extra fat + vegetables
            $extraFat = $this->pick('fat', $slot, array_merge($usedWeek, [(int) $protein['food_id']]));
            if ($protein)  $components[] = $this->calc($protein,  $remaining * 0.56);
            if ($extraFat) $components[] = $this->calc($extraFat, $remaining * 0.24);
            if ($veg1)     $components[] = $this->calc($veg1,     $remaining * 0.12);
            if ($veg2)     $components[] = $this->calc($veg2,     $remaining * 0.08);
        } else {
            if ($protein) $components[] = $this->calc($protein, $remaining * 0.47);
            if ($carb)    $components[] = $this->calc($carb,    $remaining * 0.39);
            if ($veg1)    $components[] = $this->calc($veg1,    $remaining * 0.10);
            if ($veg2)    $components[] = $this->calc($veg2,    $remaining * 0.04);
        }

        $components[] = $oilComp;
        return $components;
    }

    private function buildSnack(int $target, array $usedWeek): array
    {
        $roll = rand(0, 2);
        $excl = $usedWeek;

        if ($roll === 0) {
            $main = $this->pick('fruit', 'snack', $excl);
            if ($main) $excl[] = (int) $main['food_id'];
            $sec  = $this->pick('fat',   'snack', $excl);
        } elseif ($roll === 1) {
            $main = $this->pick('dairy', 'snack', $excl);
            if ($main) $excl[] = (int) $main['food_id'];
            $sec  = $this->pick('fruit', 'snack', $excl);
        } else {
            $main = $this->pick('fruit', 'snack', $excl);
            if ($main) $excl[] = (int) $main['food_id'];
            $sec  = $this->pick('dairy', 'snack', $excl);
        }

        if (!$main && !$sec) return $this->fallback('snack', $target);

        $components = [];
        if ($main) $components[] = $this->calc($main, $target * 0.55);
        if ($sec)  $components[] = $this->calc($sec,  $target * 0.45);
        return $components;
    }

    // ──────────────────────────────────────────────────────────
    // Food picker
    // ──────────────────────────────────────────────────────────

    private function pick(string $typeExpr, string $slot, array $excludeIds): ?array
    {
        $types      = explode('|', $typeExpr);
        $typePH     = implode(',', array_fill(0, count($types), '?'));
        $dietFilter = $this->dietFilter();

        $ints = array_unique(
            array_filter(array_map('intval', $excludeIds), fn($x) => $x > 0)
        );

        $params   = $types;
        $params[] = '%' . $this->month . '%';
        $params[] = '%' . $slot . '%';
        $excludeSQL = '';
        if (!empty($ints)) {
            $excludeSQL = ' AND id NOT IN (' . implode(',', array_fill(0, count($ints), '?')) . ')';
            $params     = array_merge($params, $ints);
        }

        $sql  = "SELECT * FROM foods
                 WHERE food_type IN ($typePH)
                   AND available_months LIKE ?
                   AND meal_slots      LIKE ?
                   $dietFilter $excludeSQL
                 ORDER BY RAND() LIMIT 6";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Return first non-disliked food
        foreach ($rows as $food) {
            if (!$this->isDisliked($food)) return $food;
        }

        // Relax variety constraint and retry once
        if (!empty($ints)) {
            return $this->pick($typeExpr, $slot, []);
        }
        return null;
    }

    private function dietFilter(): string
    {
        return match ($this->diet) {
            'vegan'      => ' AND is_vegan = 1',
            'vegetarian' => ' AND is_vegetarian = 1',
            'gf'         => ' AND is_gluten_free = 1',
            'vegan_gf'   => ' AND is_vegan = 1 AND is_gluten_free = 1',
            'keto'       => ' AND is_keto_ok = 1',
            'paleo'      => ' AND is_paleo_ok = 1',
            default      => '',
        };
    }

    // ──────────────────────────────────────────────────────────
    // Portion calculator
    // ──────────────────────────────────────────────────────────

    private function calc(array $food, float $targetCal): array
    {
        $cal100 = max(1.0, (float) $food['cal_per_100g']);
        $grams  = ($targetCal / $cal100) * 100.0;
        $grams  = (int) (round($grams / 5) * 5);
        $grams  = max((int) $food['min_serving_g'], min((int) $food['max_serving_g'], $grams));
        if ($grams < 5) $grams = 5;

        $f = $grams / 100.0;
        return [
            'food_id'      => (int)   $food['id'],
            'name_el'      =>         $food['name_el'],
            'name_en'      =>         $food['name_en'],
            'grams'        =>         $grams,
            'cal'          => (int)   round($cal100                            * $f),
            'protein_g'    => (int)   round((float) $food['protein_per_100g'] * $f),
            'carbs_g'      => (int)   round((float) $food['carbs_per_100g']   * $f),
            'fat_g'        => (int)   round((float) $food['fat_per_100g']     * $f),
            'prep_minutes' => (int)  ($food['prep_minutes'] ?? 5),
            'food_type'    =>         $food['food_type'],
        ];
    }

    private function oilComponent(): array
    {
        return [
            'food_id'      => 0,
            'name_el'      => 'Ελαιόλαδο',
            'name_en'      => 'Olive Oil',
            'grams'        => 10,
            'cal'          => 88,
            'protein_g'    => 0,
            'carbs_g'      => 0,
            'fat_g'        => 9,
            'prep_minutes' => 0,
            'food_type'    => 'fat',
        ];
    }

    // ──────────────────────────────────────────────────────────
    // Bilingual name builder
    // ──────────────────────────────────────────────────────────

    private function buildName(string $slot, array $components): array
    {
        if (empty($components)) return ['Γεύμα', 'Meal'];

        $protein = $carb = $veg1 = $veg2 = $fruit = $dairy = $fat = null;
        foreach ($components as $c) {
            switch ($c['food_type']) {
                case 'protein':
                    $protein = $protein ?? $c; break;
                case 'carb':
                    $carb    = $carb    ?? $c; break;
                case 'vegetable':
                    if (!$veg1) { $veg1 = $c; } elseif (!$veg2) { $veg2 = $c; }
                    break;
                case 'fruit':
                    $fruit   = $fruit   ?? $c; break;
                case 'dairy':
                    $dairy   = $dairy   ?? $c; break;
                case 'fat':
                    if ($c['food_id'] > 0) { $fat = $fat ?? $c; }
                    break;
            }
        }

        if ($slot === 'breakfast') {
            $main  = $carb ?? $dairy ?? $protein;
            $with  = ($protein && $protein !== $main)
                        ? $protein
                        : (($dairy && $dairy !== $main) ? $dairy : null);
            $extra = $fruit;

            $nameEl = $main['name_el'] ?? 'Πρωινό';
            $nameEn = $main['name_en'] ?? 'Breakfast';
            if ($with)  { $nameEl .= ' με '   . $with['name_el'];  $nameEn .= ' with ' . $with['name_en']; }
            if ($extra) { $nameEl .= ' και '  . $extra['name_el']; $nameEn .= ' and '  . $extra['name_en']; }

        } elseif ($slot === 'snack') {
            $visible = array_slice($components, 0, 2);
            $nameEl  = implode(' με ',   array_column($visible, 'name_el'));
            $nameEn  = implode(' with ', array_column($visible, 'name_en'));

        } else {
            // lunch / dinner: protein-centric
            $main = $protein ?? $dairy ?? $carb;
            if (!$main) return ['Γεύμα', 'Meal'];

            $nameEl = $main['name_el'];
            $nameEn = $main['name_en'];
            if ($carb && $carb !== $main) {
                $nameEl .= ' με '    . $carb['name_el'];
                $nameEn .= ' with '  . $carb['name_en'];
            }
            if ($veg1) { $nameEl .= ' και ' . $veg1['name_el']; $nameEn .= ' and ' . $veg1['name_en']; }
            if ($veg2) { $nameEl .= ', '    . $veg2['name_el']; $nameEn .= ', '    . $veg2['name_en']; }
        }

        return [$nameEl ?? 'Γεύμα', $nameEn ?? 'Meal'];
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    private function isDisliked(array $food): bool
    {
        if (empty($this->dislikes)) return false;
        $nameEl = mb_strtolower($food['name_el']);
        $nameEn = mb_strtolower($food['name_en']);
        foreach ($this->dislikes as $d) {
            if (mb_strpos($nameEl, $d) !== false || mb_strpos($nameEn, $d) !== false) {
                return true;
            }
        }
        return false;
    }

    private function fallback(string $slot, int $target): array
    {
        $df   = $this->dietFilter();
        $stmt = $this->db->prepare(
            "SELECT * FROM foods WHERE meal_slots LIKE ? $df ORDER BY RAND() LIMIT 2"
        );
        $stmt->execute(['%' . $slot . '%']);
        $foods = $stmt->fetchAll();
        if (empty($foods)) return [];
        $per = $target / count($foods);
        return array_map(fn($f) => $this->calc($f, $per), $foods);
    }
}
