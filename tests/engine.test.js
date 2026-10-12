// Run with: node tests/engine.test.js
const assert = require('assert');
const E = require('../assets/js/engine.js');
let passed = 0;
const t = (name, fn) => { fn(); passed++; console.log('ok -', name); };

const INK = ['#000'];
const heart = Uint8Array.from([0,1,0,1,0, 1,1,1,1,1, 1,1,1,1,1, 0,1,1,1,0, 0,0,1,0,0]);

t('clues for a line', () => {
  assert.deepStrictEqual(E.runs([1,1,0,1,0,1]), [[2,1],[1,1],[1,1]]);
  assert.deepStrictEqual(E.runs([0,0,0]), []);
  assert.deepStrictEqual(E.runs([1,2,2,0,1]), [[1,1],[2,2],[1,1]]);
});

t('solveLine finds forced cells', () => {
  const r = E.solveLine(Int32Array.from([3,3,3,3,3]), [[3,1]]);
  assert.deepStrictEqual(Array.from(r), [3,3,2,3,3]);
  assert.deepStrictEqual(Array.from(E.solveLine(Int32Array.from([3,3,3]), [])), [1,1,1]);
  assert.strictEqual(E.solveLine(Int32Array.from([1,1,1]), [[1,1]]), null);
});

t('unique black and white puzzle', () => {
  const s = E.solve(E.makePuzzle(5, 5, heart, INK));
  assert.ok(s.unique);
  assert.ok(Array.from(s.st).every((v, i) => v === (1 << heart[i])));
});

t('ambiguous puzzle is detected and auto-fixed', () => {
  const g = Uint8Array.from([1,0,0,0,0, 0,1,0,0,0, 0,0,0,0,0, 0,0,0,0,0, 0,0,0,0,0]);
  assert.strictEqual(E.solve(E.makePuzzle(5, 5, g, INK)).und, 4);
  const fx = E.fixGrid(5, 5, g, INK);
  assert.ok(E.solve(E.makePuzzle(5, 5, fx.g, INK)).unique);
});

t('color puzzle where different colors touch', () => {
  const pal = ['#f00', '#0f0'];
  const g = Uint8Array.from([1,1,2,2,0, 1,2,2,1,1, 0,0,2,0,0, 2,2,1,1,2, 1,0,0,0,2]);
  const fx = E.fixGrid(5, 5, g, pal);
  const s = E.solve(E.makePuzzle(5, 5, fx.g, pal));
  assert.ok(s.unique);
  assert.ok(Array.from(s.st).every((v, i) => v === (1 << fx.g[i])));
});

t('difficulty levels', () => {
  assert.strictEqual(E.level(2), 'Easy');
  assert.strictEqual(E.level(6), 'Medium');
  assert.strictEqual(E.level(10), 'Hard');
  assert.strictEqual(E.level(20), 'Expert');
  assert.strictEqual(E.level(99), 'Not unique');
});

t('fingerprint ignores rotation and mirroring', () => {
  const W = 4, H = 3, g = Uint8Array.from([1,1,0,0, 0,1,0,0, 0,1,1,1]);
  const mirror = Uint8Array.from([0,0,1,1, 0,0,1,0, 1,1,1,0]);
  const rot = new Uint8Array(12); // rotate 90° clockwise -> 3x4
  for (let r = 0; r < H; r++) for (let c = 0; c < W; c++) rot[c * H + (H - 1 - r)] = g[r * W + c];
  const f = E.fingerprint(W, H, g);
  assert.strictEqual(E.fingerprint(W, H, mirror), f);
  assert.strictEqual(E.fingerprint(H, W, rot), f);
  assert.notStrictEqual(E.fingerprint(5, 5, heart), f);
});

t('grid encoding round-trips', () => {
  const g = Uint8Array.from([0,1,2,3,4,0]);
  assert.strictEqual(E.encodeGrid(g), '012340');
  assert.deepStrictEqual(Array.from(E.decodeGrid('012340')), Array.from(g));
});

console.log(`\n${passed} tests passed`);
