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

t('analyze: unique puzzle that needs guessing is Expert, not "Not unique"', () => {
  // Rows [1 1], [2], 0, 0 and one cell per column: only 1001/0110 fits, but line logic alone cannot see it.
  const p = E.makePuzzle(4, 4, E.decodeGrid('1001011000000000'), INK);
  assert.strictEqual(E.solve(p).lineSolvable, false);
  const a = E.analyze(p);
  assert.ok(a.unique && !a.lineSolvable && !a.multiple && !a.unknown);
  assert.strictEqual(E.level(E.score(a, p)), 'Expert');
});

t('analyze: two solutions are reported as not unique', () => {
  const p = E.makePuzzle(4, 4, E.decodeGrid('0111100000000000'), INK); // 0111/1000 and 1110/0001 share clues
  const a = E.analyze(p);
  assert.ok(!a.unique && a.multiple);
  assert.strictEqual(E.level(E.score(a, p)), 'Not unique');
});

t('analyze: line-solvable puzzles skip the search', () => {
  const a = E.analyze(E.makePuzzle(5, 5, heart, INK));
  assert.ok(a.unique && a.lineSolvable && a.probes === 0);
});

t('difficulty grows with grid size and with harder logic', () => {
  // The same simple picture (a ball on a box) at four sizes: Easy at 10x10, harder as it grows.
  const pic = n => { const g = new Uint8Array(n * n); for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) { const d = Math.hypot(r - n * 0.4, c - n * 0.45) / n; g[r * n + c] = d < 0.3 || (r > n * 0.7 && c > n * 0.15 && c < n * 0.85) ? 1 : 0; } return g; };
  const lv = n => { const p = E.makePuzzle(n, n, pic(n), INK); return E.level(E.score(E.solve(p), p)); };
  assert.deepStrictEqual([10, 15, 30, 50].map(lv), ['Easy', 'Medium', 'Medium', 'Hard']);
  const easy = E.makePuzzle(5, 5, Uint8Array.from({ length: 25 }, () => 1), INK); // all filled: one sweep
  assert.strictEqual(E.level(E.score(E.solve(easy), easy)), 'Easy');
});

t('grid size follows the picture and the plan limit', () => {
  assert.deepStrictEqual(E.gridSize(200, 500, { W: 30, Hsel: 'auto', max: 30 }), { W: 12, H: 30, fitted: true }); // tall: width shrinks
  assert.deepStrictEqual(E.gridSize(200, 500, { W: 20, Hsel: 'auto', max: 50 }), { W: 20, H: 50, fitted: false });
  assert.deepStrictEqual(E.gridSize(600, 100, { W: 10, Hsel: 'auto', max: 30 }), { W: 10, H: 5, fitted: false }); // wide: at least 5 rows
  assert.deepStrictEqual(E.gridSize(100, 100, { W: 40, Hsel: '45', max: 30 }), { W: 30, H: 30, fitted: false }); // never above the plan
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
