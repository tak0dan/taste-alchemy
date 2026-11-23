'use strict';

/**
 * tests/js/engine.js — port fiel del núcleo de cálculo de Taste Alchemy.
 *
 * Es la misma física que src/Calculation/RecipeEngine.php (modo ingredientes y
 * volumen), reducida a los escalares que comparten los vectores dorados. Sirve
 * para demostrar que el modelo da idéntico resultado en el servidor (PHP) y en
 * un cliente JavaScript, sin dependencias.
 */

const JUICE_YIELD = 0.18;
const SUGAR_VOLUME_FACTOR = 0.15;
const LOSS_PCT = { hot: 15.0, hybrid: 8.0, limoncello: 0.0 };
// base_fruit_weight / base_vodka por preset (cereza 500/500, hibisco 30/500).
const FRUIT_TO_VODKA = { cereza: 1.0, hibisco: 0.06, frutos_rojos: 1.0 };

const round = (value, decimals) => {
  const f = Math.pow(10, decimals);
  return Math.round(value * f) / f;
};

function specificGravity(brix) {
  return 1.0 + 0.004 * Math.max(0, brix);
}

function sugarGlToBrix(sugarGl) {
  sugarGl = Math.max(0, sugarGl);
  if (sugarGl === 0) return 0;
  let brix = sugarGl / 10.0;
  for (let i = 0; i < 8; i++) {
    brix = sugarGl / (10.0 * specificGravity(brix));
  }
  return brix;
}

function freezingPointC(abv, brix) {
  abv = Math.max(0, abv);
  brix = Math.max(0, brix);
  return -1.0 * (0.0034 * abv * abv + 0.42 * abv + 0.11 * brix);
}

function solveFromVolume(desired, fruitToVodka, sugarRatio, waterRatio, juiceYield, sugarVolumeFactor, lossPct) {
  const loss = lossPct / 100.0;
  const refVodka = 1000.0;
  const refFruit = refVodka * fruitToVodka;
  const refSugar = refFruit * sugarRatio;
  const refWater = refFruit * waterRatio;
  const refNetVodka = refVodka * (1.0 - loss);
  const refJuice = refFruit * juiceYield;
  const refFinal = refNetVodka + refWater + refJuice + refSugar * sugarVolumeFactor;
  const yieldFactor = refFinal / refVodka;
  const vodka = desired / yieldFactor;
  const fruit = vodka * fruitToVodka;
  return [round(vodka, 2), round(fruit, 2)];
}

function calculate(input = {}) {
  const base = input.base || 'cereza';
  const extraction = input.extraction || 'limoncello';
  const mode = input.mode === 'volumen' ? 'volumen' : 'ingredientes';
  const baseAbv = input.base_abv_pct != null ? input.base_abv_pct : 40;
  const lossPct = input.extraction_loss_pct != null ? input.extraction_loss_pct : (LOSS_PCT[extraction] ?? 0);
  const sugarRatio = input.sugar_ratio != null ? input.sugar_ratio : 0.4;
  const waterRatio = input.water_ratio != null ? input.water_ratio : 0.5;
  const juiceYield = input.juice_yield != null ? input.juice_yield : JUICE_YIELD;
  const sugarVolumeFactor = input.sugar_volume_factor != null ? input.sugar_volume_factor : SUGAR_VOLUME_FACTOR;
  const fruitToVodka = input.fruit_to_vodka_ratio != null ? input.fruit_to_vodka_ratio : (FRUIT_TO_VODKA[base] ?? 1.0);

  let vodkaMl = input.vodka_ml != null ? input.vodka_ml : 500;
  let fruitG = input.ingredient_g != null ? input.ingredient_g : 500;
  const desiredVolume = input.desired_volume_ml != null ? input.desired_volume_ml : 1000;

  if (mode === 'volumen') {
    [vodkaMl, fruitG] = solveFromVolume(desiredVolume, fruitToVodka, sugarRatio, waterRatio, juiceYield, sugarVolumeFactor, lossPct);
  }

  const loss = lossPct / 100.0;
  const sugarG = input.sugar_g != null && input.sugar_g !== '' ? input.sugar_g : round(fruitG * sugarRatio, 2);
  const waterMl = input.water_ml != null && input.water_ml !== '' ? input.water_ml : round(fruitG * waterRatio, 2);

  const netVodkaMl = vodkaMl * (1.0 - loss);
  const juiceMl = fruitG * juiceYield;
  const sugarVolumeMl = sugarG * sugarVolumeFactor;
  const finalVolumeMl = netVodkaMl + waterMl + juiceMl + sugarVolumeMl;

  const alcoholMl = vodkaMl * (baseAbv / 100.0) * (1.0 - loss);
  const abv = finalVolumeMl > 0 ? (alcoholMl / finalVolumeMl) * 100.0 : 0;
  const sugarGl = finalVolumeMl > 0 ? sugarG / (finalVolumeMl / 1000.0) : 0;
  const brix = sugarGlToBrix(sugarGl);

  return {
    abv_pct: round(abv, 3),
    alcohol_ml: round(alcoholMl, 2),
    final_ml: round(finalVolumeMl, 2),
    net_vodka_ml: round(netVodkaMl, 2),
    juice_ml: round(juiceMl, 2),
    sugar_volume_ml: round(sugarVolumeMl, 2),
    water_ml: round(waterMl, 2),
    sugar_g: round(sugarG, 1),
    sugar_gl: round(sugarGl, 2),
    brix: round(brix, 2),
    freezing_point_c: round(freezingPointC(abv, brix), 2),
  };
}

module.exports = { calculate, specificGravity, sugarGlToBrix, freezingPointC };