/* Built-in pictures, drawn in code (no copyright issues, no downloads).
   starter = everyone; nature / letters / numbers = Picture Vault. */
(function () {
  'use strict';
  const C_RED = '#c81d25', C_GRN = '#2d6a4f', C_BLU = '#1d3d6b', C_BRN = '#7a4a22', C_ORG = '#c4501f', C_GLD = '#9a6a00', C_INK = '#1f2328', C_PNK = '#b5179e';
  const PICS = {
    starter: {
      Heart(x) { x.fillStyle = C_RED; x.beginPath(); x.moveTo(150, 268); x.bezierCurveTo(40, 190, 8, 130, 40, 80); x.bezierCurveTo(72, 28, 135, 40, 150, 96); x.bezierCurveTo(165, 40, 228, 28, 260, 80); x.bezierCurveTo(292, 130, 260, 190, 150, 268); x.fill(); },
      Star(x) { x.fillStyle = C_GLD; x.beginPath(); for (let i = 0; i < 10; i++) { const r = i % 2 ? 60 : 142, a = -Math.PI / 2 + i * Math.PI / 5; x.lineTo(150 + r * Math.cos(a), 160 + r * Math.sin(a)); } x.closePath(); x.fill(); },
      House(x) { x.fillStyle = C_RED; x.beginPath(); x.moveTo(25, 145); x.lineTo(150, 32); x.lineTo(275, 145); x.fill(); x.fillRect(200, 50, 32, 70); x.fillStyle = C_BLU; x.fillRect(58, 140, 184, 138); x.fillStyle = C_BRN; x.fillRect(128, 196, 46, 82); x.fillStyle = '#fff'; x.fillRect(78, 166, 36, 36); x.fillRect(190, 166, 36, 36); },
      Fish(x) { x.fillStyle = C_ORG; x.beginPath(); x.ellipse(132, 150, 100, 64, 0, 0, Math.PI * 2); x.fill(); x.fillStyle = C_BLU; x.beginPath(); x.moveTo(210, 150); x.lineTo(288, 82); x.lineTo(288, 218); x.fill(); x.fillStyle = '#fff'; x.beginPath(); x.arc(82, 132, 14, 0, Math.PI * 2); x.fill(); x.fillRect(150, 100, 10, 100); },
      Mug(x) { x.fillStyle = C_BLU; x.strokeStyle = C_BLU; x.fillRect(55, 110, 160, 140); x.lineWidth = 24; x.beginPath(); x.arc(215, 178, 40, -Math.PI / 2, Math.PI / 2); x.stroke(); x.fillStyle = C_INK; x.fillRect(25, 255, 220, 20); x.strokeStyle = '#555b63'; x.lineWidth = 13; x.lineCap = 'round'; for (const sx of [95, 135, 175]) { x.beginPath(); x.moveTo(sx, 92); x.bezierCurveTo(sx - 18, 70, sx + 18, 52, sx, 26); x.stroke(); } },
      Cat(x) { x.fillStyle = C_INK; x.beginPath(); x.ellipse(150, 170, 118, 104, 0, 0, Math.PI * 2); x.fill(); x.beginPath(); x.moveTo(42, 140); x.lineTo(52, 20); x.lineTo(132, 78); x.fill(); x.beginPath(); x.moveTo(258, 140); x.lineTo(248, 20); x.lineTo(168, 78); x.fill(); x.fillStyle = C_GRN; x.beginPath(); x.ellipse(106, 158, 18, 24, 0, 0, Math.PI * 2); x.ellipse(194, 158, 18, 24, 0, 0, Math.PI * 2); x.fill(); x.fillStyle = C_PNK; x.beginPath(); x.moveTo(132, 200); x.lineTo(168, 200); x.lineTo(150, 220); x.fill(); },
    },
    nature: {
      Tree(x) { const t = (a, b, c) => { x.beginPath(); x.moveTo(...a); x.lineTo(...b); x.lineTo(...c); x.fill(); }; x.fillStyle = C_GRN; t([150, 14], [72, 108], [228, 108]); t([150, 66], [48, 176], [252, 176]); t([150, 122], [30, 246], [270, 246]); x.fillStyle = C_BRN; x.fillRect(128, 240, 44, 50); },
      Strawberry(x) { x.fillStyle = C_RED; x.beginPath(); x.moveTo(150, 282); x.bezierCurveTo(60, 230, 30, 130, 70, 92); x.bezierCurveTo(110, 62, 190, 62, 230, 92); x.bezierCurveTo(270, 130, 240, 230, 150, 282); x.fill(); x.fillStyle = '#fff'; for (const [a, b] of [[110, 130], [150, 120], [190, 130], [95, 175], [135, 165], [175, 165], [205, 180], [125, 210], [170, 210], [150, 250]]) { x.beginPath(); x.ellipse(a, b, 6, 9, 0, 0, Math.PI * 2); x.fill(); } x.fillStyle = C_GRN; x.beginPath(); x.moveTo(150, 40); for (let i = 0; i < 7; i++) { const a = Math.PI * (0.1 + i * 0.8 / 6); x.lineTo(150 - Math.cos(a) * 95, 92 - Math.sin(a) * 22 * (i % 2 ? 0.3 : 1.6)); } x.closePath(); x.fill(); x.fillRect(143, 14, 14, 40); },
      Ladybug(x) { x.fillStyle = C_RED; x.beginPath(); x.arc(150, 170, 115, 0, Math.PI * 2); x.fill(); x.fillStyle = C_INK; x.beginPath(); x.arc(150, 62, 52, 0, Math.PI * 2); x.fill(); x.fillRect(146, 60, 8, 225); for (const [a, b, r] of [[95, 140, 22], [205, 140, 22], [90, 210, 18], [210, 210, 18], [120, 250, 14], [180, 250, 14]]) { x.beginPath(); x.arc(a, b, r, 0, Math.PI * 2); x.fill(); } },
      Mushroom(x) { x.fillStyle = C_RED; x.beginPath(); x.ellipse(150, 140, 135, 100, 0, Math.PI, 0); x.fill(); x.fillRect(15, 138, 270, 14); x.fillStyle = '#fff'; for (const [a, b, r] of [[90, 100, 18], [150, 70, 20], [210, 100, 18], [120, 125, 12], [185, 128, 12]]) { x.beginPath(); x.arc(a, b, r, 0, Math.PI * 2); x.fill(); } x.fillStyle = C_BRN; x.fillRect(110, 152, 80, 130); },
      Flower(x) { x.fillStyle = C_GRN; x.fillRect(143, 150, 14, 140); x.beginPath(); x.ellipse(105, 230, 40, 16, -0.5, 0, Math.PI * 2); x.ellipse(195, 215, 40, 16, 0.5, 0, Math.PI * 2); x.fill(); x.fillStyle = C_PNK; for (let i = 0; i < 6; i++) { const a = i * Math.PI / 3; x.beginPath(); x.ellipse(150 + Math.cos(a) * 60, 105 + Math.sin(a) * 60, 42, 30, a, 0, Math.PI * 2); x.fill(); } x.fillStyle = C_GLD; x.beginPath(); x.arc(150, 105, 34, 0, Math.PI * 2); x.fill(); },
      Smile(x) { x.fillStyle = C_GLD; x.beginPath(); x.arc(150, 150, 135, 0, Math.PI * 2); x.fill(); x.fillStyle = '#fff'; x.strokeStyle = '#fff'; x.beginPath(); x.arc(103, 112, 20, 0, Math.PI * 2); x.arc(197, 112, 20, 0, Math.PI * 2); x.fill(); x.lineWidth = 20; x.lineCap = 'round'; x.beginPath(); x.arc(150, 158, 72, .18 * Math.PI, .82 * Math.PI); x.stroke(); },
    },
    letters: {}, numbers: {},
  };
  const GLYPH_FONTS = ['Arial Black, Arial, sans-serif', 'Georgia, serif', 'Impact, Arial, sans-serif', 'Courier New, monospace', 'Trebuchet MS, sans-serif', 'Verdana, sans-serif'];
  'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').forEach(ch => { PICS.letters['Letter ' + ch] = { glyph: ch }; });
  '0123456789'.split('').forEach(ch => { PICS.numbers['Number ' + ch] = { glyph: ch }; });

  function find(name) { for (const k in PICS) if (PICS[k][name]) return PICS[k][name]; return null; }
  function canvas(name, v = {}) {
    const c = document.createElement('canvas'); c.width = c.height = 300;
    const x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, 300, 300);
    if (v.mirror) { x.translate(300, 0); x.scale(-1, 1); }
    const pic = find(name);
    if (pic && pic.glyph) { x.fillStyle = v.ink || C_BLU; x.textAlign = 'center'; x.textBaseline = 'middle'; x.font = `bold 270px ${v.font || GLYPH_FONTS[0]}`; x.fillText(pic.glyph, 150, 162); }
    else if (pic) pic(x);
    return c;
  }
  window.Pictures = { PICS, GLYPH_FONTS, find, canvas };
})();
