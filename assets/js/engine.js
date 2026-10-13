/* Nonogram Studio — puzzle engine.
   Pure logic: picture -> grid, clues, line solver, uniqueness check, auto-fix,
   difficulty and duplicate fingerprint. No AI, no network.
   Works in the browser (window.NonoEngine) and in Node (module.exports) for tests. */
(function (root) {
  'use strict';

  const INK = '#16191d';
  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
  const hex2 = n => Math.round(clamp(n, 0, 255)).toString(16).padStart(2, '0');

  /* ---------- Picture -> grid (browser only: needs a canvas) ---------- */
  /** Grid size for a picture. 'auto' height follows the picture; if that is taller than the plan allows
      (o.max), the width shrinks instead so the picture keeps its shape. Returns {W, H, fitted}. */
  function gridSize(sw, sh, o) {
    const max = o.max || 50;
    let W = clamp(o.W, 5, max), H, fitted = false;
    if (o.Hsel === 'auto') {
      H = Math.round(W * sh / sw);
      if (H > max) { W = clamp(Math.round(max * sw / sh), 5, max); H = max; fitted = W !== o.W; }
      H = clamp(H, 5, max);
    } else H = clamp(+o.Hsel, 5, max);
    return { W, H, fitted };
  }

  // o: {W, Hsel ('auto' | number), max, thr, invert, colors (1-4), clean, contrast (-100..100), brightness (-100..100)}
  function gridFromSource(src, o) {
    const sw = src.naturalWidth || src.width, sh = src.naturalHeight || src.height;
    const { W, H, fitted } = gridSize(sw, sh, o), k = 8;
    const c = document.createElement('canvas'); c.width = W * k; c.height = H * k;
    const x = c.getContext('2d', { willReadFrequently: true });
    x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); x.imageSmoothingQuality = 'high';
    const s = Math.min(c.width / sw, c.height / sh), dw = sw * s, dh = sh * s;
    const box = { x: Math.round((c.width - dw) / 2), y: Math.round((c.height - dh) / 2), w: Math.round(dw), h: Math.round(dh) };
    x.drawImage(src, box.x, box.y, dw, dh);
    const d = x.getImageData(0, 0, c.width, c.height).data, N = W * H;
    if ((o.colors || 1) > 1) return Object.assign(quantizeColor(d, c.width, W, H, k, box, o), { fitted });
    const R = new Float32Array(N), G = new Float32Array(N), B = new Float32Array(N);
    for (let y = 0; y < c.height; y++) for (let xx = 0; xx < c.width; xx++) {
      const i = (y * c.width + xx) * 4, j = ((y / k) | 0) * W + ((xx / k) | 0);
      R[j] += d[i]; G[j] += d[i + 1]; B[j] += d[i + 2];
    }
    const kk = k * k, ct = 1 + (o.contrast || 0) / 100, br = (o.brightness || 0) * 1.28;
    for (let i = 0; i < N; i++) {
      R[i] = clamp((R[i] / kk - 128) * ct + 128 + br, 0, 255);
      G[i] = clamp((G[i] / kk - 128) * ct + 128 + br, 0, 255);
      B[i] = clamp((B[i] / kk - 128) * ct + 128 + br, 0, 255);
    }
    return Object.assign(quantize({ W, H, R, G, B }, o), { fitted });
  }

  /* Color pictures: work per pixel, not per cell average, so cells on an edge do not become a blended
     extra color. Background = the picture's own border color (not assumed white); a pixel is drawn when it
     differs enough from it (so light colors such as yellow are kept). Each cell takes its majority color. */
  function lab(r, g, b) {
    const f = v => { v /= 255; return v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; };
    const R = f(r), G = f(g), B = f(b);
    const X = (R * 0.4124 + G * 0.3576 + B * 0.1805) / 0.95047, Y = R * 0.2126 + G * 0.7152 + B * 0.0722, Z = (R * 0.0193 + G * 0.1192 + B * 0.9505) / 1.08883;
    const t = v => (v > 0.008856 ? Math.cbrt(v) : 7.787 * v + 16 / 116);
    return [116 * t(Y) - 16, 500 * (t(X) - t(Y)), 200 * (t(Y) - t(Z))];
  }
  const dE = (a, b) => Math.sqrt((a[0] - b[0]) ** 2 + (a[1] - b[1]) ** 2 + (a[2] - b[2]) ** 2);

  function quantizeColor(d, cw, W, H, k, box, o) {
    const ct = 1 + (o.contrast || 0) / 100, br = (o.brightness || 0) * 1.28;
    const adj = v => clamp((v - 128) * ct + 128 + br, 0, 255);
    const P = W * H * k * k, L = new Float32Array(P * 3), RGB = new Uint8Array(P * 3), inside = new Uint8Array(P);
    for (let p = 0; p < P; p++) {
      const px = p % cw, py = (p / cw) | 0, i = p * 4;
      const r = adj(d[i]), g = adj(d[i + 1]), b = adj(d[i + 2]), l = lab(r, g, b);
      RGB[p * 3] = r; RGB[p * 3 + 1] = g; RGB[p * 3 + 2] = b; L[p * 3] = l[0]; L[p * 3 + 1] = l[1]; L[p * 3 + 2] = l[2];
      inside[p] = px >= box.x && px < box.x + box.w && py >= box.y && py < box.y + box.h ? 1 : 0;
    }
    const at = p => [L[p * 3], L[p * 3 + 1], L[p * 3 + 2]];
    // Background: median of the picture's outer ring.
    const ring = [];
    for (let p = 0; p < P; p++) {
      if (!inside[p]) continue;
      const px = p % cw - box.x, py = ((p / cw) | 0) - box.y;
      if (px < 2 || py < 2 || px >= box.w - 2 || py >= box.h - 2) ring.push(p);
    }
    const med = ch => { const v = ring.map(p => L[p * 3 + ch]).sort((a, b) => a - b); return v.length ? v[v.length >> 1] : [100, 0, 0][ch]; };
    const bg = [med(0), med(1), med(2)];
    // Cut-off slider (20-240, higher = more filled) -> how different from the background a pixel must be.
    const T = 8 + (240 - o.thr) * 0.2;
    const fg = new Uint8Array(P), pts = [];
    for (let p = 0; p < P; p++) if (inside[p] && dE(at(p), bg) > T) { fg[p] = 1; pts.push(p); }
    const g = new Uint8Array(W * H);
    if (!pts.length) return { W, H, g, palette: [INK] };

    // Centers: farthest-point start, then k-means in Lab on a sample of drawn pixels.
    const step = Math.max(1, Math.floor(pts.length / 6000)), sample = pts.filter((_, i) => i % step === 0);
    let cents = [at(sample.reduce((a, p) => (dE(at(p), bg) > dE(at(a), bg) ? p : a), sample[0]))];
    while (cents.length < o.colors) {
      let best = -1, bd = -1;
      for (const p of sample) { const m = Math.min(...cents.map(c => dE(at(p), c))); if (m > bd) { bd = m; best = p; } }
      if (bd < 15) break; // remaining pixels are near an existing color
      cents.push(at(best));
    }
    const nearest = (l, cs) => { let b = 0, bd = Infinity; cs.forEach((c, ci) => { const v = dE(l, c); if (v < bd) { bd = v; b = ci; } }); return b; };
    for (let it = 0; it < 8; it++) {
      const acc = cents.map(() => [0, 0, 0, 0]);
      for (const p of sample) { const a = acc[nearest(at(p), cents)]; a[0] += L[p * 3]; a[1] += L[p * 3 + 1]; a[2] += L[p * 3 + 2]; a[3]++; }
      // Drop colors that cover almost nothing; they are usually edge pixels.
      cents = acc.filter(a => a[3] >= sample.length * 0.015).map(a => [a[0] / a[3], a[1] / a[3], a[2] / a[3]]);
    }
    // Vote per cell: background (0) or one of the colors (1..K).
    // Each color also keeps a coarse histogram of its pixels: the palette uses the most common shade, so
    // when the picture has more colors than asked for, the palette shows a real color, not a blend.
    const K = cents.length, votes = new Uint16Array(W * H * (K + 1)), hist = cents.map(() => new Map());
    for (let p = 0; p < P; p++) {
      const cell = ((((p / cw) | 0) / k) | 0) * W + (((p % cw) / k) | 0);
      let lbl = 0;
      if (fg[p]) {
        const ci = nearest(at(p), cents), r = RGB[p * 3], gg = RGB[p * 3 + 1], b = RGB[p * 3 + 2], key = (r >> 4) << 8 | (gg >> 4) << 4 | (b >> 4);
        const h = hist[ci], e = h.get(key); if (e) { e[0] += r; e[1] += gg; e[2] += b; e[3]++; } else h.set(key, [r, gg, b, 1]);
        lbl = ci + 1;
      }
      votes[cell * (K + 1) + lbl]++;
    }
    const sum = hist.map(h => { let best = [0, 0, 0, 1], n = 0; for (const e of h.values()) if (e[3] > n) { n = e[3]; best = e; } return best; });
    for (let i = 0; i < W * H; i++) {
      let b = 0, bv = -1;
      for (let l = 0; l <= K; l++) { const v = votes[i * (K + 1) + l]; if (v > bv) { bv = v; b = l; } }
      g[i] = b;
    }
    if (o.clean) despeckle(g, W, H);
    // Palette from the real pixel colors, dark to light, keeping only colors some cell uses.
    const hex = s => '#' + [s[0], s[1], s[2]].map(v => hex2(v / s[3])).join('');
    const lum = s => (0.299 * s[0] + 0.587 * s[1] + 0.114 * s[2]) / s[3];
    const used = [...new Set(g)].filter(v => v).map(v => v - 1).sort((a, b) => lum(sum[a]) - lum(sum[b]));
    const remap = new Map(used.map((ci, n) => [ci + 1, n + 1]));
    for (let i = 0; i < W * H; i++) g[i] = g[i] ? remap.get(g[i]) : 0;
    return { W, H, g, palette: used.length ? used.map(ci => hex(sum[ci])) : [INK] };
  }

  function quantize(img, o) {
    const { W, H, R, G, B } = img, N = W * H;
    const L = i => 0.299 * R[i] + 0.587 * G[i] + 0.114 * B[i];
    const g = new Uint8Array(N); let palette = [INK];
    if ((o.colors || 1) <= 1) {
      for (let i = 0; i < N; i++) { let on = L(i) < o.thr; if (o.invert) on = !on; g[i] = on ? 1 : 0; }
    } else {
      const pts = []; for (let i = 0; i < N; i++) if (L(i) < o.thr) pts.push(i);
      if (pts.length) {
        const dist = (i, c) => (R[i] - c[0]) ** 2 + (G[i] - c[1]) ** 2 + (B[i] - c[2]) ** 2;
        let dark = pts[0]; for (const i of pts) if (L(i) < L(dark)) dark = i;
        const cents = [[R[dark], G[dark], B[dark]]];
        while (cents.length < o.colors) {
          let best = -1, bd = -1;
          for (const i of pts) { const m = Math.min(...cents.map(c => dist(i, c))); if (m > bd) { bd = m; best = i; } }
          if (bd < 900) break;
          cents.push([R[best], G[best], B[best]]);
        }
        const asg = new Int32Array(N).fill(-1);
        for (let it = 0; it < 10; it++) {
          const acc = cents.map(() => [0, 0, 0, 0]);
          for (const i of pts) {
            let b = 0, bd = Infinity;
            cents.forEach((c, ci) => { const dd = dist(i, c); if (dd < bd) { bd = dd; b = ci; } });
            asg[i] = b; const a = acc[b]; a[0] += R[i]; a[1] += G[i]; a[2] += B[i]; a[3]++;
          }
          acc.forEach((a, ci) => { if (a[3]) cents[ci] = [a[0] / a[3], a[1] / a[3], a[2] / a[3]]; });
        }
        const lum = c => 0.299 * c[0] + 0.587 * c[1] + 0.114 * c[2];
        const used = [...new Set(pts.map(i => asg[i]))].sort((a, b) => lum(cents[a]) - lum(cents[b]));
        const remap = new Map(used.map((ci, n) => [ci, n + 1]));
        palette = used.map(ci => '#' + cents[ci].map(hex2).join(''));
        for (const i of pts) g[i] = remap.get(asg[i]);
      }
    }
    if (o.clean) despeckle(g, W, H);
    return { W, H, g, palette };
  }

  function despeckle(g, W, H) {
    const o = g.slice(); const at = (r, c) => r >= 0 && r < H && c >= 0 && c < W ? o[r * W + c] : 0;
    for (let r = 0; r < H; r++) for (let c = 0; c < W; c++) {
      const i = r * W + c; let n8 = 0;
      for (let dr = -1; dr <= 1; dr++) for (let dc = -1; dc <= 1; dc++) if ((dr || dc) && at(r + dr, c + dc)) n8++;
      if (o[i] && n8 === 0) g[i] = 0;
      else if (!o[i]) { const n = [at(r - 1, c), at(r + 1, c), at(r, c - 1), at(r, c + 1)]; if (n[0] && n.every(v => v === n[0])) g[i] = n[0]; }
    }
  }

  /* ---------- Clues ---------- */
  function runs(arr) {
    const out = []; let c = 0, n = 0;
    for (const v of arr) { if (v && v === c) n++; else { if (c) out.push([n, c]); c = v; n = v ? 1 : 0; } }
    if (c) out.push([n, c]);
    return out;
  }
  function makePuzzle(W, H, g, palette) {
    const rowClues = [], colClues = [];
    for (let r = 0; r < H; r++) rowClues.push(runs(g.subarray ? g.subarray(r * W, r * W + W) : g.slice(r * W, r * W + W)));
    for (let c = 0; c < W; c++) { const col = []; for (let r = 0; r < H; r++) col.push(g[r * W + c]); colClues.push(runs(col)); }
    return {
      W, H, g, palette, rowClues, colClues,
      maxR: Math.max(1, ...rowClues.map(a => a.length)), maxC: Math.max(1, ...colClues.map(a => a.length))
    };
  }

  /* ---------- Line solver: every cell holds a bitmask (bit 0 = empty, bit c = color c) ---------- */
  function solveLine(cells, clues) {
    const n = cells.length, k = clues.length, S = k + 1;
    const no0 = new Int32Array(n + 1); for (let i = 0; i < n; i++) no0[i + 1] = no0[i] + ((cells[i] & 1) ? 0 : 1);
    const bad = {};
    for (const [, c] of clues) {
      if (bad[c]) continue; const a = new Int32Array(n + 1), bit = 1 << c;
      for (let i = 0; i < n; i++) a[i + 1] = a[i] + ((cells[i] & bit) ? 0 : 1); bad[c] = a;
    }
    const F = new Uint8Array((n + 1) * S);
    for (let i = n; i >= 0; i--) for (let j = k; j >= 0; j--) {
      let v = 0;
      if (j === k) v = no0[n] - no0[i] === 0 ? 1 : 0;
      else {
        if (i < n && (cells[i] & 1) && F[(i + 1) * S + j]) v = 1;
        if (!v) {
          const [L, c] = clues[j];
          if (i + L <= n && bad[c][i + L] - bad[c][i] === 0) {
            const gap = j + 1 < k && clues[j + 1][1] === c;
            if (gap) { if (i + L < n && (cells[i + L] & 1) && F[(i + L + 1) * S + j + 1]) v = 1; }
            else if (F[(i + L) * S + j + 1]) v = 1;
          }
        }
      }
      F[i * S + j] = v;
    }
    if (!F[0]) return null;
    const Rr = new Uint8Array((n + 1) * S), cm = new Int32Array(n); Rr[0] = 1;
    for (let i = 0; i <= n; i++) for (let j = 0; j <= k; j++) {
      if (!Rr[i * S + j]) continue;
      if (j === k) { for (let t = i; t < n; t++) cm[t] |= 1; continue; }
      if (i < n && (cells[i] & 1) && F[(i + 1) * S + j]) { cm[i] |= 1; Rr[(i + 1) * S + j] = 1; }
      const [L, c] = clues[j];
      if (i + L <= n && bad[c][i + L] - bad[c][i] === 0) {
        const gap = j + 1 < k && clues[j + 1][1] === c, bit = 1 << c;
        if (gap) {
          if (i + L < n && (cells[i + L] & 1) && F[(i + L + 1) * S + j + 1]) {
            for (let t = i; t < i + L; t++) cm[t] |= bit; cm[i + L] |= 1; Rr[(i + L + 1) * S + j + 1] = 1;
          }
        } else if (F[(i + L) * S + j + 1]) { for (let t = i; t < i + L; t++) cm[t] |= bit; Rr[(i + L) * S + j + 1] = 1; }
      }
    }
    const out = new Int32Array(n);
    for (let t = 0; t < n; t++) { out[t] = cells[t] & cm[t]; if (!out[t]) return null; }
    return out;
  }

  const undecided = st => { let n = 0; for (const v of st) if (v & (v - 1)) n++; return n; };

  // Line logic until nothing changes. st (optional) is a starting state, changed in place.
  // Also records how hard the logic was: open1 = share of cells still open after the first sweep,
  // steps = line solves that found something new.
  function propagate(p, st, budget) {
    const { W, H } = p;
    const rd = new Uint8Array(H).fill(1), cd = new Uint8Array(W).fill(1);
    let rounds = 0, any = true, steps = 0, open1 = 0;
    while (any) {
      any = false; rounds++;
      for (let r = 0; r < H; r++) {
        if (!rd[r]) continue; rd[r] = 0;
        const line = st.slice(r * W, r * W + W), res = solveLine(line, p.rowClues[r]);
        if (budget) budget.n--;
        if (!res) return { bad: true };
        let ch = false;
        for (let c = 0; c < W; c++) if (res[c] !== line[c]) { st[r * W + c] = res[c]; cd[c] = 1; any = ch = true; }
        if (ch) steps++;
      }
      for (let c = 0; c < W; c++) {
        if (!cd[c]) continue; cd[c] = 0;
        const line = new Int32Array(H); for (let r = 0; r < H; r++) line[r] = st[r * W + c];
        const res = solveLine(line, p.colClues[c]);
        if (budget) budget.n--;
        if (!res) return { bad: true };
        let ch = false;
        for (let r = 0; r < H; r++) if (res[r] !== line[r]) { st[r * W + c] = res[r]; rd[r] = 1; any = ch = true; }
        if (ch) steps++;
      }
      if (rounds === 1) open1 = undecided(st) / (W * H);
      if (rounds > 600) break;
    }
    return { rounds: rounds - 1, steps, open1 };
  }

  /** Line logic only. unique = solvable without guessing (which also proves there is one solution). */
  function solve(p) {
    const st = new Int32Array(p.W * p.H).fill((1 << (p.palette.length + 1)) - 1);
    const r = propagate(p, st);
    if (r.bad) return { bad: true, und: p.W * p.H, unique: false, lineSolvable: false, multiple: false, unknown: false };
    const und = undecided(st);
    return { st, und, rounds: r.rounds, steps: r.steps, open1: r.open1, unique: und === 0, lineSolvable: und === 0, multiple: false, unknown: false, probes: 0 };
  }

  /* ---------- Full check: line logic, then a bounded search for puzzles that need guessing ----------
     Returns solve()'s result plus: unique (exactly one solution, proven), lineSolvable, multiple (two solutions
     found), unknown (search budget ran out: treated as not unique). Budget = line solves allowed in the search. */
  function analyze(p, budgetLines) {
    const s = solve(p);
    if (s.bad || s.lineSolvable) return s;
    const K = p.palette.length, budget = { n: budgetLines || 4000 * Math.max(1, Math.round(p.W * p.H / 225)) };
    let found = 0, probes = 0;
    (function dfs(st) {
      if (found > 1 || budget.n <= 0) return;
      if (propagate(p, st, budget).bad) return;
      let i = -1, best = 99;
      for (let t = 0; t < st.length; t++) { const v = st[t]; if (v & (v - 1)) { let b = 0; for (let x = v; x; x &= x - 1) b++; if (b < best) { best = b; i = t; if (b === 2) break; } } }
      if (i < 0) { found++; return; }
      for (let v = 0; v <= K && found < 2 && budget.n > 0; v++) if (st[i] & (1 << v)) { probes++; const s2 = st.slice(); s2[i] = 1 << v; dfs(s2); }
    })(s.st.slice());
    const unknown = found < 2 && budget.n <= 0;
    return Object.assign(s, { unique: found === 1 && !unknown, multiple: found > 1, unknown, probes });
  }

  /* ---------- Difficulty ---------- */
  // How much logic the solver needed (extra sweeps, cells still open after the first sweep, lines revisited)
  // plus how big the grid is. Calibrated on picture-like grids so a typical 10x10 is Easy, 15x15 spreads over
  // Easy-Hard and 30x30+ is Medium-Expert. Unique puzzles that need guessing (analyze) are always Expert.
  function score(s, p) {
    if (!s.unique) return 99;
    const lines = p.W + p.H;
    const logic = Math.max(0, s.rounds - 1) + 4 * (s.open1 || 0) + 1.5 * Math.max(0, (s.steps || lines) / lines - 1);
    const size = 2.5 * Math.log2(Math.max(1, Math.sqrt(p.W * p.H) / 10));
    const v = 0.75 * logic + size;
    if (s.lineSolvable === false) return Math.min(98, Math.max(14, v + 2 + Math.log2(1 + (s.probes || 0))));
    return Math.min(98, v);
  }
  const LEVELS = ['Easy', 'Medium', 'Hard', 'Expert'];
  function level(v) { return v >= 99 ? 'Not unique' : v <= 4 ? 'Easy' : v <= 8 ? 'Medium' : v <= 13 ? 'Hard' : 'Expert'; }

  /* ---------- Auto-fix: flip the fewest cells until the puzzle has one solution ---------- */
  function fixGrid(W, H, g, palette, maxIt = 30, maxCand = 36, onFlip) {
    g = g.slice(); let flips = 0;
    const nb = i => {
      const r = (i / W) | 0, c = i % W;
      for (const [dr, dc] of [[0, 1], [0, -1], [1, 0], [-1, 0]]) {
        const rr = r + dr, cc = c + dc;
        if (rr >= 0 && rr < H && cc >= 0 && cc < W && g[rr * W + cc]) return g[rr * W + cc];
      }
      return 1;
    };
    for (let it = 0; it < maxIt; it++) {
      const s = solve(makePuzzle(W, H, g, palette)); if (s.unique) break;
      const und = []; s.st.forEach((v, i) => { if (v & (v - 1)) und.push(i); });
      const step = Math.max(1, Math.ceil(und.length / maxCand)), cand = und.filter((_, i) => i % step === 0);
      let best = null;
      for (const i of cand) {
        const old = g[i]; g[i] = old ? 0 : nb(i);
        const t = solve(makePuzzle(W, H, g, palette));
        if (!t.bad && (!best || t.und < best.und)) best = { i, v: g[i], und: t.und };
        g[i] = old;
      }
      if (!best) break;
      g[best.i] = best.v; flips++; onFlip && onFlip(best.i, best.v);
    }
    return { g, flips };
  }

  /* ---------- Duplicate fingerprint: same picture rotated or mirrored gives the same hash ---------- */
  function transforms(W, H, g) {
    const out = [];
    const at = (r, c) => g[r * W + c];
    const mk = (w, h, f) => { const a = new Array(w * h); for (let r = 0; r < h; r++) for (let c = 0; c < w; c++) a[r * w + c] = f(r, c); return w + 'x' + h + ':' + a.join(''); };
    out.push(mk(W, H, (r, c) => at(r, c)));
    out.push(mk(W, H, (r, c) => at(r, W - 1 - c)));
    out.push(mk(W, H, (r, c) => at(H - 1 - r, c)));
    out.push(mk(W, H, (r, c) => at(H - 1 - r, W - 1 - c)));
    out.push(mk(H, W, (r, c) => at(c, r)));
    out.push(mk(H, W, (r, c) => at(H - 1 - c, r)));
    out.push(mk(H, W, (r, c) => at(c, W - 1 - r)));
    out.push(mk(H, W, (r, c) => at(H - 1 - c, W - 1 - r)));
    return out;
  }
  function fnv(str) {
    let h1 = 0x811c9dc5, h2 = 0x01000193 ^ str.length;
    for (let i = 0; i < str.length; i++) { const ch = str.charCodeAt(i); h1 = Math.imul(h1 ^ ch, 16777619); h2 = Math.imul(h2 ^ ch, 2246822519); }
    return (h1 >>> 0).toString(16).padStart(8, '0') + (h2 >>> 0).toString(16).padStart(8, '0');
  }
  function fingerprint(W, H, g) { return fnv(transforms(W, H, Array.from(g)).sort()[0]); }

  /* ---------- Compact storage format: one base-36 character per cell ---------- */
  const encodeGrid = g => Array.from(g, v => v.toString(36)).join('');
  const decodeGrid = s => Uint8Array.from(s, ch => parseInt(ch, 36));

  const E = { INK, clamp, gridSize, gridFromSource, quantize, despeckle, runs, makePuzzle, solveLine, solve, analyze, score, level, LEVELS, fixGrid, fingerprint, encodeGrid, decodeGrid };
  if (typeof module !== 'undefined' && module.exports) module.exports = E; else root.NonoEngine = E;
})(typeof self !== 'undefined' ? self : this);
