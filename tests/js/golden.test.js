'use strict';

/**
 * tests/js/golden.test.js — vectores dorados compartidos con PHP.
 *
 *   node tests/js/golden.test.js
 *
 * Lee tests/vectors/engine.json (congelado desde el motor PHP) y comprueba que
 * el port JavaScript da el mismo resultado. Sin dependencias: sólo node.
 */

const fs = require('fs');
const path = require('path');
const { calculate, specificGravity, sugarGlToBrix, freezingPointC } = require('./engine.js');

const vectors = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'vectors', 'engine.json'), 'utf8'));

let pass = 0;
let fail = 0;
const failures = [];

function check(name, cond, detail) {
  if (cond) {
    pass++;
    console.log(`  ✓ ${name}`);
  } else {
    fail++;
    failures.push(`${name} ${detail || ''}`);
    console.log(`  ✗ ${name} ${detail || ''}`);
  }
}

function near(name, expected, actual, tol) {
  check(name, Math.abs(expected - actual) <= tol, `esperado ~${expected} (±${tol}), obtenido ${actual}`);
}

console.log('Taste Alchemy · vectores dorados JS ↔ PHP\n');

// Motor: cada vector congelado desde PHP.
for (const v of vectors.engine) {
  const r = calculate(v.input);
  const e = v.expect;
  near(`${v.name} · ABV`, e.abv_pct, r.abv_pct, 0.01);
  near(`${v.name} · volumen final`, e.final_ml, r.final_ml, 0.5);
  near(`${v.name} · alcohol`, e.alcohol_ml, r.alcohol_ml, 0.5);
  near(`${v.name} · vodka neto`, e.net_vodka_ml, r.net_vodka_ml, 0.5);
  near(`${v.name} · jugo`, e.juice_ml, r.juice_ml, 0.5);
  near(`${v.name} · volumen azúcar`, e.sugar_volume_ml, r.sugar_volume_ml, 0.5);
  near(`${v.name} · agua`, e.water_ml, r.water_ml, 0.5);
  near(`${v.name} · azúcar g`, e.sugar_g, r.sugar_g, 0.5);
  near(`${v.name} · g/L`, e.sugar_gl, r.sugar_gl, 0.5);
  near(`${v.name} · Brix`, e.brix, r.brix, 0.05);
  near(`${v.name} · congelación`, e.freezing_point_c, r.freezing_point_c, 0.05);
}

// Escalars: azúcar, frío y dilución.
for (const s of vectors.scalars.sugar) {
  near(`azúcar SG(${s.brix})`, s.sg, specificGravity(s.brix), 1e-9);
  near(`azúcar g/L(${s.brix})`, s.gl, s.brix * 10 * specificGravity(s.brix), 1e-6);
}
for (const s of vectors.scalars.slushy) {
  near(`frío fp(${s.abv},${s.brix})`, s.fp, freezingPointC(s.abv, s.brix), 0.01);
}
near('azúcar inversa Brix(216)≈20', 20, sugarGlToBrix(216), 0.05);

// Pearson 40/0 → 30 (port inline).
const pearson = (a, b, target) => {
  const pa = Math.abs(target - b);
  const pb = Math.abs(a - target);
  const total = pa + pb;
  return { part_a: pa / total, part_b: pb / total };
};
const p = pearson(40, 0, 30);
near('Pearson parte A', vectors.scalars.dilution.pearson_40_0_30.part_a, p.part_a, 1e-4);
near('Pearson parte B', vectors.scalars.dilution.pearson_40_0_30.part_b, p.part_b, 1e-4);

// Diluir 1000 mL de 40 % a 20 % → 1000 mL de agua.
const dilute = (vol, cur, target) => {
  const alcohol = vol * cur / 100;
  const final = alcohol / (target / 100);
  return final - vol;
};
near('dilución agua 40→20', vectors.scalars.dilution.dilute_1000_40_to_20.water_ml, dilute(1000, 40, 20), 0.01);

console.log(`\n${pass} superadas · ${fail} fallidas`);
if (fail > 0) {
  console.log('\nFallos:\n  ' + failures.join('\n  '));
  process.exit(1);
}