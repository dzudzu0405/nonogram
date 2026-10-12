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
  // o: {W, Hsel ('auto' | number), thr, invert, colors (1-4), clean, contrast (-100..100), brightness (-100..100)}
  function gridFromSource(src, o) {
    const sw = src.naturalWidth || src.width, sh = src.naturalHeight || src.height;
    const W = o.W, H = o.Hsel === 'auto' ? clamp(Math.round(W * sh / sw), 5, 50) : +o.Hsel, k = 8;
    const c = document.createElement('canvas'); c.width = W * k; c.height = H * k;
    const x = c.getContext('2d', { willReadFrequently: true });
    x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); x.imageSmoothingQuality = 'high';
    const s = Math.min(c.width / sw, c.height / sh), dw = sw * s, dh = sh * s;
    x.drawImage(src, (c.width - dw) / 2, (c.height - dh) / 2, dw, dh);
    const d = x.getImageData(0, 0, c.width, c.height).data, N = W * H;
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
    return quantize({ W, H, R, G, B }, o);
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

  function solve(p) {
    const { W, H } = p, K = p.palette.length, all = (1 << (K + 1)) - 1;
    const st = new Int32Array(W * H).fill(all);
    const rd = new Uint8Array(H).fill(1), cd = new Uint8Array(W).fill(1);
    let rounds = 0, any = true;
    while (any) {
      any = false; rounds++;
      for (let r = 0; r < H; r++) {
        if (!rd[r]) continue; rd[r] = 0;
        const line = st.slice(r * W, r * W + W), res = solveLine(line, p.rowClues[r]);
        if (!res) return { bad: true, und: W * H };
        for (let c = 0; c < W; c++) if (res[c] !== line[c]) { st[r * W + c] = res[c]; cd[c] = 1; any = true; }
      }
      for (let c = 0; c < W; c++) {
        if (!cd[c]) continue; cd[c] = 0;
        const line = new Int32Array(H); for (let r = 0; r < H; r++) line[r] = st[r * W + c];
        const res = solveLine(line, p.colClues[c]);
        if (!res) return { bad: true, und: W * H };
        for (let r = 0; r < H; r++) if (res[r] !== line[r]) { st[r * W + c] = res[r]; rd[r] = 1; any = true; }
      }
      if (rounds > 600) break;
    }
    let und = 0; for (const v of st) if (v & (v - 1)) und++;
    return { st, und, rounds: rounds - 1, unique: und === 0 };
  }

  /* ---------- Difficulty ---------- */
  // Logic passes, normalised to a 15x15 grid, nudged by clue density.
  function score(s, p) {
    if (!s.unique) return 99;
    const clues = p.rowClues.reduce((a, r) => a + r.length, 0) + p.colClues.reduce((a, c) => a + c.length, 0);
    const density = clues / (p.W + p.H);
    return s.rounds / Math.sqrt(p.W * p.H / 225) + Math.max(0, density - 2) * 0.8;
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

  const E = { INK, clamp, gridFromSource, quantize, despeckle, runs, makePuzzle, solveLine, solve, score, level, LEVELS, fixGrid, fingerprint, encodeGrid, decodeGrid };
  if (typeof module !== 'undefined' && module.exports) module.exports = E; else root.NonoEngine = E;
})(typeof self !== 'undefined' ? self : this);
