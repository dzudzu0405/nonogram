/* Drawing for canvas previews and PDF export (same layout code for both). */
(function () {
  'use strict';
  const E = window.NonoEngine;
  const PAPER = { ink: '#16191d', line: '#a7b0ba', clue: '#f2f5f8', clue2: '#e4e9ef', amb: '#f4b400', text: '#16191d', white: '#ffffff' };
  const FONTS = { mono: '"JetBrains Mono", Consolas, monospace', display: '"Bricolage Grotesque", "Segoe UI", sans-serif', body: 'Archivo, "Segoe UI", sans-serif' };
  // Page sizes in points (1 in = 72 pt)
  const PAGES = { letter: [612, 792], '8x10': [576, 720], '6x9': [432, 648], a4: [595.28, 841.89], a5: [419.53, 595.28] };

  const hex2 = n => Math.round(Math.max(0, Math.min(255, n))).toString(16).padStart(2, '0');
  function inkOn(hex) { const n = parseInt(hex.slice(1), 16); return (0.299 * (n >> 16) + 0.587 * (n >> 8 & 255) + 0.114 * (n & 255)) > 150 ? '#16191d' : '#ffffff'; }
  function shade(hex, a) { const n = parseInt(hex.slice(1), 16); return '#' + [n >> 16, n >> 8 & 255, n & 255].map(v => hex2(a < 0 ? v * (1 + a) : v + (255 - v) * a)).join(''); }

  // R is a renderer: rect, line, text (canvas or PDF). o: {solution, st, reveal, clues, thin, thick, fs}
  function drawPuzzle(R, p, x0, y0, cell, o) {
    const color = p.palette.length > 1, cu = cell * 0.8;
    const mr = o.clues === false ? 0 : p.maxR, mc = o.clues === false ? 0 : p.maxC;
    const gx = x0 + mr * cu, gy = y0 + mc * cu, gw = p.W * cell, gh = p.H * cell;
    R.rect(gx, gy, gw, gh, PAPER.white);
    if (mr) {
      R.rect(x0, gy, mr * cu, gh, PAPER.clue); R.rect(gx, y0, gw, mc * cu, PAPER.clue);
      for (let b = 5; b < p.H; b += 10) R.rect(x0, gy + b * cell, mr * cu, Math.min(5, p.H - b) * cell, PAPER.clue2);
      for (let b = 5; b < p.W; b += 10) R.rect(gx + b * cell, y0, Math.min(5, p.W - b) * cell, mc * cu, PAPER.clue2);
    }
    if (o.solution) for (let r = 0; r < p.H; r++) for (let c = 0; c < p.W; c++) {
      const i = r * p.W + c, v = p.g[i];
      if (o.reveal && !o.reveal(r, c)) continue;
      const amb = o.st && (o.st[i] & (o.st[i] - 1));
      if (amb) { R.rect(gx + c * cell, gy + r * cell, cell, cell, PAPER.amb); if (v) R.rect(gx + (c + .25) * cell, gy + (r + .25) * cell, cell * .5, cell * .5, p.palette[v - 1]); }
      else if (v) R.rect(gx + c * cell, gy + r * cell, cell, cell, p.palette[v - 1]);
    }
    for (let c = 0; c <= p.W; c++) { const th = c % 5 === 0 || c === p.W; R.line(gx + c * cell, th && mc ? y0 : gy, gx + c * cell, gy + gh, th ? o.thick : o.thin, th ? PAPER.ink : PAPER.line); }
    for (let r = 0; r <= p.H; r++) { const th = r % 5 === 0 || r === p.H; R.line(th && mr ? x0 : gx, gy + r * cell, gx + gw, gy + r * cell, th ? o.thick : o.thin, th ? PAPER.ink : PAPER.line); }
    if (mr) {
      const fs = cell * (o.fs || 0.56);
      const clue = (v, col, cx, cy, bw, bh) => {
        if (color && col) { const pc = p.palette[col - 1]; R.rect(cx - bw * .42, cy - bh * .4, bw * .84, bh * .8, pc); R.text(v, cx, cy, fs, inkOn(pc)); }
        else R.text(v, cx, cy, fs, PAPER.text);
      };
      p.rowClues.forEach((cl, r) => { const a = cl.length ? cl : [[0, 0]]; a.forEach(([v, col], k) => clue(v, col, gx - (a.length - k - 0.5) * cu, gy + (r + 0.5) * cell, cu, cell)); });
      p.colClues.forEach((cl, c) => { const a = cl.length ? cl : [[0, 0]]; a.forEach(([v, col], k) => clue(v, col, gx + (c + 0.5) * cell, gy - (a.length - k - 0.5) * cu, cell, cu)); });
    }
    return { gx, gy, w: mr * cu + gw, h: mc * cu + gh };
  }

  function wrapLines(x, t, w) {
    const out = [];
    for (const para of String(t).split('\n')) {
      let line = '';
      for (const word of para.split(' ')) { const test = line ? line + ' ' + word : word; if (x.measureText(test).width > w && line) { out.push(line); line = word; } else line = test; }
      out.push(line);
    }
    return out;
  }
  function canvasR(x) {
    return {
      rect(a, b, w, h, c) { x.fillStyle = c; x.fillRect(a, b, w, h); },
      line(a, b, c, d, w, col, dash) { x.save(); x.strokeStyle = col; x.lineWidth = w; if (dash) x.setLineDash(dash); x.beginPath(); x.moveTo(a, b); x.lineTo(c, d); x.stroke(); x.restore(); },
      text(s, a, b, size, col, o = {}) {
        x.save(); x.fillStyle = col; x.font = `${o.weight || 700} ${size}px ${FONTS[o.font || 'mono']}`; x.textAlign = o.align || 'center'; x.textBaseline = 'middle';
        if (o.angle) { x.translate(a, b); x.rotate(o.angle * Math.PI / 180); x.fillText(String(s), 0, 0); } else x.fillText(String(s), a, b + size * 0.04);
        x.restore();
      },
      para(t, a, b, w, size, col, lh = 1.4, o = {}) {
        x.save(); x.fillStyle = col; x.font = `${o.weight || 400} ${size}px ${FONTS[o.font || 'body']}`; x.textAlign = o.align || 'left'; x.textBaseline = 'top';
        const lines = wrapLines(x, t, w); lines.forEach((l, i) => x.fillText(l, o.align === 'center' ? a + w / 2 : a, b + i * size * lh)); x.restore();
        return lines.length * size * lh;
      },
    };
  }
  function pdfR(doc) {
    const setF = o => doc.setFont('helvetica', (o.weight || 700) >= 600 ? 'bold' : 'normal');
    return {
      rect(a, b, w, h, c) { doc.setFillColor(c); doc.rect(a, b, w, h, 'F'); },
      line(a, b, c, d, w, col, dash) { doc.setDrawColor(col); doc.setLineWidth(w); if (dash) doc.setLineDashPattern(dash, 0); doc.line(a, b, c, d); if (dash) doc.setLineDashPattern([], 0); },
      text(s, a, b, size, col, o = {}) {
        setF(o); doc.setFontSize(size); doc.setTextColor(col); s = String(s);
        if (o.angle === 90) { const tw = doc.getTextWidth(s); doc.text(s, a - size * 0.35, b - tw / 2, { angle: -90 }); return; }
        doc.text(s, a, b, { align: o.align || 'center', baseline: 'middle' });
      },
      para(t, a, b, w, size, col, lh = 1.4, o = {}) {
        setF({ weight: o.weight || 400 }); doc.setFontSize(size); doc.setTextColor(col);
        const lines = doc.splitTextToSize(String(t), w);
        lines.forEach((l, i) => doc.text(l, o.align === 'center' ? a + w / 2 : a, b + i * size * lh, { align: o.align || 'left', baseline: 'top' }));
        return lines.length * size * lh;
      },
    };
  }

  /** Small solution image (data URL) for lists and cards. */
  function thumb(W, H, g, palette, k = 4) {
    const c = document.createElement('canvas'); c.width = W * k; c.height = H * k;
    const x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height);
    for (let i = 0; i < g.length; i++) if (g[i]) { x.fillStyle = palette[g[i] - 1] || PAPER.ink; x.fillRect((i % W) * k, ((i / W) | 0) * k, k, k); }
    return c.toDataURL();
  }

  /** Puzzle pages (one per page) followed by solutions, four per page. items: [{name, p, score}] */
  function buildPDF(items, opts) {
    const [PW, PH] = PAGES[opts.page] || PAGES.letter;
    const doc = new window.jspdf.jsPDF({ unit: 'pt', format: [PW, PH], orientation: 'p' }), R = pdfR(doc);
    const M = 0.6 * 72; let pg = 0;
    const newPage = () => { if (pg) doc.addPage(); pg++; };
    const footer = () => R.text(String(pg), PW / 2, PH - M / 2, 9, '#666666', { weight: 400 });
    items.forEach((b, n) => {
      newPage();
      const p = b.p;
      R.text(`Puzzle ${n + 1}`, M, M + 4, 18, PAPER.ink, { align: 'left' });
      R.text(`${E.level(b.score)} · ${p.W} × ${p.H}${p.palette.length > 1 ? ' · ' + p.palette.length + ' colors' : ''}`, PW - M, M + 4, 10, '#666666', { align: 'right', weight: 400 });
      const uw = p.W + p.maxR * 0.8, uh = p.H + p.maxC * 0.8, bw = PW - 2 * M, bh = PH - 2 * M - 40;
      const cell = Math.min(bw / uw, bh / uh, 28);
      drawPuzzle(R, p, M + (bw - uw * cell) / 2, M + 30 + (bh - uh * cell) / 2, cell, { thin: 0.35, thick: 1.1 });
      footer();
    });
    for (let s = 0; s < items.length; s += 4) {
      newPage();
      R.text('Solutions', M, M + 4, 18, PAPER.ink, { align: 'left' });
      const gap = 16, bw = (PW - 2 * M - gap) / 2, bh = (PH - 2 * M - 40 - gap) / 2;
      items.slice(s, s + 4).forEach((b, k) => {
        const p = b.p, x = M + (k % 2) * (bw + gap), y = M + 30 + ((k / 2) | 0) * (bh + gap);
        R.text(`Puzzle ${s + k + 1}: ${b.name}`, x, y + 6, 9.5, '#333333', { align: 'left' });
        const cell = Math.min(bw / p.W, (bh - 18) / p.H);
        drawPuzzle(R, p, x + (bw - p.W * cell) / 2, y + 16, cell, { solution: true, clues: false, thin: 0.25, thick: 0.7 });
      });
      footer();
    }
    return doc;
  }

  window.Render = { PAPER, PAGES, inkOn, shade, drawPuzzle, canvasR, pdfR, thumb, buildPDF };
})();
