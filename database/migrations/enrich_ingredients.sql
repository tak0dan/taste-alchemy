-- Enriquecimiento nutricional generado por tools/enrich_ingredients.py
-- Valores por 100 g comestible. Brix ≈ azúcar en fruta.
UPDATE ingredients SET source = 'referencia', sugar_g_per_100 = 10.0, brix = 10.0, water_pct = 84.0 WHERE slug = 'cereza-acida';
UPDATE ingredients SET source = 'referencia', sugar_g_per_100 = 2.5, brix = 2.5, water_pct = 89.0 WHERE slug = 'limon';
UPDATE ingredients SET source = 'referencia', sugar_g_per_100 = 8.0, brix = 8.0, water_pct = 87.0 WHERE slug = 'naranja';
UPDATE ingredients SET source = 'referencia', sugar_g_per_100 = 6.0, brix = 6.0, water_pct = 88.0 WHERE slug = 'frutos-rojos';
UPDATE ingredients SET source = 'referencia', water_pct = 10.0 WHERE slug = 'hibisco-seco';
UPDATE ingredients SET source = 'referencia', water_pct = 12.0 WHERE slug = 'azahar';
UPDATE ingredients SET source = 'referencia', water_pct = 85.0 WHERE slug = 'hierbabuena';
UPDATE ingredients SET source = 'referencia', water_pct = 70.0 WHERE slug = 'romero';
UPDATE ingredients SET source = 'referencia', water_pct = 12.0 WHERE slug = 'canela-en-rama';
UPDATE ingredients SET source = 'referencia', water_pct = 25.0 WHERE slug = 'vainilla';
