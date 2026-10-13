// Checks the puzzle engine against brute force on many generated cases. Takes a few seconds.
//   node tests/engine.fuzz.js
// Run it after any change to the solver, analyze() or clue generation.
const assert = require('assert');
const E = require('../assets/js/engine.js');
let seed = 12345;
const rnd = () => ((seed = (seed * 1103515245 + 12345) >>> 0) / 4294967296), ri = n => Math.floor(rnd() * n);
const t = (name, fn) => { const t0 = Date.now(); fn(); console.log('ok -', name, `(${Date.now() - t0} ms)`); };

// Every filling of a line that fits the cells' allowed values and the clue.
function bruteLine(cells, clues, K) {
  const n = cells.length, out = new Array(n).fill(0), vals = new Array(n); let any = false;
  (function rec(i) {
    if (i === n) {
      const r = E.runs(vals);
      if (r.length === clues.length && r.every(([len, c], j) => len === clues[j][0] && c === clues[j][1])) { any = true; vals.forEach((v, j) => out[j] |= 1 << v); }
      return;
    }
    for (let v = 0; v <= K; v++) if (cells[i] & (1 << v)) { vals[i] = v; rec(i + 1); }
  })(0);
  return any ? out : null;
}

t('solveLine equals brute force (B&W and 2-3 colors, partly known lines)', () => {
  for (let it = 0; it < 4000; it++) {
    const K = 1 + ri(3), n = 1 + ri(10), all = (1 << (K + 1)) - 1;
    const truth = Array.from({ length: n }, () => (rnd() < 0.5 ? 0 : 1 + ri(K)));
    const clues = rnd() < 0.85 ? E.runs(truth) : E.runs(Array.from({ length: n }, () => (rnd() < 0.5 ? 0 : 1 + ri(K))));
    const cells = Int32Array.from({ length: n }, () => { if (rnd() < 0.6) return all; let m = 0; while (!m) m = ri(all + 1); return m; });
    const a = E.solveLine(cells, clues), b = bruteLine(cells, clues, K);
    assert.deepStrictEqual(a && Array.from(a), b, JSON.stringify({ cells: Array.from(cells), clues }));
  }
});

// Group every possible grid of a small size by its clues, then check analyze() on each.
function exhaustive(W, H, K, every) {
  const N = W * H, total = (K + 1) ** N, byClue = new Map(), key = p => JSON.stringify([p.rowClues, p.colClues]);
  const grid = m => { const g = new Uint8Array(N); for (let i = 0; i < N; i++) { g[i] = m % (K + 1); m = Math.floor(m / (K + 1)); } return g; };
  const pal = ['#000', '#c00', '#00c'].slice(0, K);
  for (let m = 0; m < total; m++) { const k = key(E.makePuzzle(W, H, grid(m), pal)); byClue.set(k, (byClue.get(k) || 0) + 1); }
  let checked = 0, expert = 0;
  for (let m = 0; m < total; m += every) {
    const p = E.makePuzzle(W, H, grid(m), pal), n = byClue.get(key(p)), a = E.analyze(p);
    assert.ok(!a.unknown, 'search budget ran out on a tiny grid');
    assert.strictEqual(a.unique, n === 1, `grid ${m}: ${n} solutions, analyze says unique=${a.unique}`);
    assert.strictEqual(a.multiple, n > 1, `grid ${m}: ${n} solutions, analyze says multiple=${a.multiple}`);
    if (a.lineSolvable) assert.ok(a.unique, 'line-solvable must be unique');
    if (a.unique && !a.lineSolvable) expert++;
    checked++;
  }
  return { checked, expert };
}
t('analyze() matches exhaustive counting: every 4x4 black-and-white grid (sampled)', () => {
  const r = exhaustive(4, 4, 1, 5); assert.ok(r.expert > 0, 'expected some unique puzzles that need guessing');
});
t('analyze() matches exhaustive counting: 3x3 with 2 colors', () => { exhaustive(3, 3, 2, 1); });

t('fingerprint: same hash for all 8 rotations and mirrors', () => {
  for (let it = 0; it < 200; it++) {
    const W = 3 + ri(8), H = 3 + ri(8), g = Uint8Array.from({ length: W * H }, () => (rnd() < 0.5 ? 1 : 0)), f = E.fingerprint(W, H, g);
    const rot = new Uint8Array(W * H); for (let r = 0; r < W; r++) for (let c = 0; c < H; c++) rot[r * H + c] = g[(H - 1 - c) * W + r];
    const mir = new Uint8Array(W * H); for (let r = 0; r < H; r++) for (let c = 0; c < W; c++) mir[r * W + c] = g[r * W + (W - 1 - c)];
    assert.strictEqual(E.fingerprint(H, W, rot), f); assert.strictEqual(E.fingerprint(W, H, mir), f);
  }
});

console.log('\nengine fuzz passed');
