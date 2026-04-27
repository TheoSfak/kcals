-- KCALS Migration 019 – Treat substantial prepared mixed dishes as lunch meals
--
-- Hearty cooked composite dishes (for example moussaka, pastitsio, stifado,
-- bolognese) should not be scheduled as evening meals when users mark them as
-- must-include foods. Keep lighter mixed meals, breakfast items, and snacks as-is.

UPDATE `foods`
SET `meal_slots` = 'lunch'
WHERE `food_type` = 'mixed'
  AND `prep_minutes` >= 35
  AND `cal_per_100g` >= 140
  AND `meal_slots` NOT LIKE '%breakfast%'
  AND `meal_slots` NOT LIKE '%snack%';

INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('019_substantial_mixed_lunch_slots.sql');
