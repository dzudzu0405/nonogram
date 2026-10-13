/* Runs Auto-fix off the main thread so big grids do not freeze the editor.
   Message in: {id, W, H, g, palette, maxIt}. Message out: {id, flips: [[cell, value], ...]} or {id, error}. */
importScripts('engine.js' + self.location.search);

self.onmessage = e => {
  const { id, W, H, g, palette, maxIt } = e.data;
  try {
    const flips = [];
    self.NonoEngine.fixGrid(W, H, Uint8Array.from(g), palette, maxIt, 36, (i, v) => flips.push([i, v]));
    self.postMessage({ id, flips });
  } catch (err) {
    self.postMessage({ id, error: String(err && err.message || err) });
  }
};
