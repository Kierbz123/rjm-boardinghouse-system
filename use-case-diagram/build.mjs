// Generates the RJM use case diagram (SVG + draw.io) from one data model and
// validates the geometry (no line through an oval, no overlaps, all in canvas).
// Run: node build.mjs   (PNG: render with headless Edge, see render.cmd)
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const DIR = path.dirname(fileURLToPath(import.meta.url));
const W = 1900;
const P = 40; // row pitch of stacked child ovals
const ADMIN_ROWS = 18, ADMIN_GAPS = 5, ADMIN_TOP = 120;
const YS = ADMIN_TOP + (ADMIN_ROWS - 1) * P + ADMIN_GAPS * 14 + 294; // y of the shared band centre (Staff / Boarder level)
const BOTTOM = ADMIN_TOP + (ADMIN_ROWS - 1) * 40 + 5 * 14 + 294 + 190 + 95 + 17; // last shared child bottom
const H = BOTTOM + 70;
const BOX = { x1: 150, y1: 60, x2: 1750, y2: H - 30 };

// ---- model -----------------------------------------------------------------
const nodes = {};
const edges = []; // {type: assoc|include|extend|gen, from, to}
const actors = {
  admin:   { label: 'Admin',             x: 75,   y: 520,  side: 'L' },
  staff:   { label: 'Maintenance Staff', x: 75,   y: 0, side: 'L' },
  visitor: { label: 'Website Visitor',   x: 1825, y: 480,  side: 'R' },
  boarder: { label: 'Boarder',           x: 1825, y: 0, side: 'R' },
};

function uc(id, label, cx, cy, kind, w) {
  nodes[id] = { id, label, cx, cy, kind, w: w ?? (kind === 'parent' ? 190 : 250), h: kind === 'parent' ? 38 : 34 };
}
// parent on one side, children fanned out toward the centre
function group(id, label, side, ys, kids, { actor, rel = 'include', parentY } = {}) {
  const pcx = side === 'L' ? 285 : 1615, ccx = side === 'L' ? 610 : 1290;
  const py = parentY ?? ys.reduce((a, b) => a + b, 0) / ys.length;
  uc(id, label, pcx, py, 'parent');
  kids.forEach((k, i) => {
    const kid = `${id}.${i}`;
    uc(kid, k.label ?? k, ccx, ys[i], 'child');
    const ext = (k.rel ?? rel) === 'extend';
    edges.push(ext ? { type: 'extend', from: kid, to: id } : { type: 'include', from: id, to: kid });
  });
  if (actor) edges.push({ type: 'assoc', from: actor, to: id });
}

// Admin groups (stacked, top-left)
let y = ADMIN_TOP;
const adminGroups = [
  ['adm', 'Administration', ['View Command-Center dashboard', 'Manage staff accounts', 'Log expenses', 'View occupancy trend', 'Review inquiry and link to boarder']],
  ['bor', 'Manage Boarders', ['Add boarder and update status', 'Archive or restore boarder', 'Assign room and bed']],
  ['prop', 'Manage Property', ['Manage rooms and room types', 'Manage beds']],
  ['pay', 'Manage Payments', ['Review, approve or reject payment', 'Record in-person payment', 'Export financial ledger']],
  ['pen', 'Manage Penalties', ['Configure penalty rules', 'Run penalty check', 'Issue penalty or mark paid']],
  ['bill', 'Manage Billing', ['Manage electricity billing', 'Send rent-due reminders']],
];
for (const [id, label, kids] of adminGroups) {
  const ys = kids.map((_, i) => y + i * P);
  group(id, label, 'L', ys, kids, { actor: 'admin' });
  y = ys[ys.length - 1] + P + 14;
}

actors.staff.y = actors.boarder.y = YS;
// Maintenance Staff groups (left, around the shared band)
group('mm', 'Manage Maintenance', 'L', [YS - 230, YS - 190], ['View and update maintenance queue', 'View maintenance history'], { actor: 'staff' });
group('sos', 'Respond to Emergencies', 'L', [YS + 182, YS + 222], ['Acknowledge SOS alert', 'Resolve SOS alert'], { actor: 'staff' });
group('inq', 'Inquiry Center', 'L', [YS + 268], ['Log walk-in or phone inquiry'], { actor: 'staff' });

// Boarder groups (right, around the shared band)
group('mr', 'Maintenance Request', 'R', [YS - 270, YS - 230, YS - 190],
  ['Submit maintenance request', { label: 'Preview priority score', rel: 'extend' }, { label: 'Get AI repair suggestion', rel: 'extend' }], { actor: 'boarder' });
group('rent', 'Pay Rent', 'R', [YS + 182], ['Submit proof of payment'], { actor: 'boarder' });
group('emg', 'Emergency', 'R', [YS + 240], ['Trigger SOS alert'], { actor: 'boarder' });

// Website Visitor
group('pin', 'Public Inquiry', 'R', [480], ['Submit public room inquiry'], { actor: 'visitor' });

// Shared band (centre): Account Access, Notifications, Incidents
const shared = [
  ['acc', 'Account Access', YS - 190, ['Log in / log out', 'Manage own profile and password']],
  ['not', 'Notifications', YS, ['View notifications']],
  ['inc', 'Incidents', YS + 190, ['Report an incident', 'View incident history']],
];
for (const [id, label, py, kids] of shared) {
  uc(id, label, 950, py, 'parent', 200);
  kids.forEach((k, i) => {
    const cx = kids.length === 1 ? 950 : 950 + (i === 0 ? -122 : 122);
    uc(`${id}.${i}`, k, cx, py + 95, 'child', 232);
    edges.push({ type: 'include', from: id, to: `${id}.${i}` });
  });
  edges.push({ type: 'assoc', from: 'staff', to: id });
  edges.push({ type: 'assoc', from: 'boarder', to: id });
}
// Admin specializes Maintenance Staff (inherits its use cases, incl. the shared ones)
edges.push({ type: 'gen', from: 'admin', to: 'staff' });

// ---- geometry ----------------------------------------------------------------
const ACT_ATT = (a) => ({ x: a.x + (a.side === 'L' ? 17 : -17), y: a.y - 12 });
function perim(n, tx, ty) { // point on ellipse n toward (tx,ty)
  const dx = tx - n.cx, dy = ty - n.cy;
  const t = 1 / Math.sqrt((dx / (n.w / 2)) ** 2 + (dy / (n.h / 2)) ** 2);
  return { x: n.cx + dx * t, y: n.cy + dy * t };
}
function endpoints(e) {
  if (e.type === 'assoc') {
    const a = ACT_ATT(actors[e.from]), n = nodes[e.to];
    return [a, { x: n.cx + (actors[e.from].side === 'L' ? -n.w / 2 : n.w / 2), y: n.cy }];
  }
  if (e.type === 'gen') { // vertical, under Admin's name label to Staff's head
    const a = actors[e.from], s = actors[e.to];
    return [{ x: a.x, y: a.y + 68 }, { x: s.x, y: s.y - 40 }];
  }
  const f = nodes[e.from], t = nodes[e.to];
  return [perim(f, t.cx, t.cy), perim(t, f.cx, f.cy)];
}
edges.forEach((e) => { [e.p, e.q] = endpoints(e); });

// include/extend label placement
const LBL = { include: '<<include>>', extend: '<<extend>>' };
const FS = 10;
for (const e of edges) {
  if (!LBL[e.type]) continue;
  e.text = LBL[e.type];
  const tw = e.text.length * FS * 0.56, th = FS + 2;
  const mx = (e.p.x + e.q.x) / 2, my = (e.p.y + e.q.y) / 2;
  const vertical = Math.abs(e.p.x - e.q.x) < 90 && Math.abs(e.p.y - e.q.y) < 70 && nodes[e.from].cx >= 800 && nodes[e.from].cx <= 1100;
  if (vertical) { // short centre-band arrows: label beside the line, outside the ovals
    const left = e.q.x < e.p.x - 2;
    const dx = left ? -(tw + 8) : 8;
    e.box = { x: mx + dx, y: my - th / 2, w: tw, h: th };
    e.anchor = 'start';
  } else {
    e.box = { x: mx - tw / 2, y: my - th / 2, w: tw, h: th };
    e.anchor = 'middle';
  }
}

// ---- validation ----------------------------------------------------------------
const problems = [];
const inEll = (n, x, y, m = 0) => ((x - n.cx) / (n.w / 2 + m)) ** 2 + ((y - n.cy) / (n.h / 2 + m)) ** 2 < 1;
const seg = (e) => [e.p, e.q];
function sample(p, q, n = 240) { return Array.from({ length: n + 1 }, (_, i) => ({ x: p.x + (q.x - p.x) * i / n, y: p.y + (q.y - p.y) * i / n })); }
function segInt(a, b, c, d) {
  const o = (p, q, r) => (q.x - p.x) * (r.y - p.y) - (q.y - p.y) * (r.x - p.x);
  const d1 = o(a, b, c), d2 = o(a, b, d), d3 = o(c, d, a), d4 = o(c, d, b);
  return d1 * d2 < 0 && d3 * d4 < 0;
}
const name = (e) => `${e.type}:${e.from}->${e.to}`;
// 1. lines must not pass through ovals other than their own endpoints
for (const e of edges) {
  if (e.type === 'gen') continue;
  for (const n of Object.values(nodes)) {
    if (n.id === e.from || n.id === e.to) continue;
    if (sample(...seg(e)).some((s) => inEll(n, s.x, s.y, 3))) problems.push(`${name(e)} passes through "${n.label}"`);
  }
}
// 2. line crossings
for (let i = 0; i < edges.length; i++) for (let j = i + 1; j < edges.length; j++) {
  const a = edges[i], b = edges[j];
  if (segInt(a.p, a.q, b.p, b.q)) problems.push(`crossing: ${name(a)} x ${name(b)}`);
}
// 3. ovals must not overlap each other or leave the boundary
const ns = Object.values(nodes);
for (let i = 0; i < ns.length; i++) {
  const a = ns[i];
  if (a.cx - a.w / 2 < BOX.x1 + 10 || a.cx + a.w / 2 > BOX.x2 - 10 || a.cy - a.h / 2 < BOX.y1 + 40 || a.cy + a.h / 2 > BOX.y2 - 10) problems.push(`"${a.label}" too close to / outside boundary`);
  for (let j = i + 1; j < ns.length; j++) {
    const b = ns[j];
    for (let k = 0; k < 360; k += 3) {
      const x = a.cx + (a.w / 2 + 3) * Math.cos(k * Math.PI / 180), y = a.cy + (a.h / 2 + 3) * Math.sin(k * Math.PI / 180);
      if (inEll(b, x, y)) { problems.push(`ovals too close: "${a.label}" / "${b.label}"`); break; }
    }
  }
}
// 4. relationship labels: clear of ovals, other lines, other labels
for (const e of edges) {
  if (!e.box) continue;
  const b = e.box;
  const inBox = (x, y, m = 1) => x > b.x - m && x < b.x + b.w + m && y > b.y - m && y < b.y + b.h + m;
  outer: for (const n of ns) for (let x = b.x; x <= b.x + b.w; x += 4) for (let y = b.y; y <= b.y + b.h; y += 3) if (inEll(n, x, y, 1)) { problems.push(`label of ${name(e)} touches "${n.label}"`); break outer; }
  for (const o of edges) {
    if (o === e) continue;
    if (sample(o.p, o.q, 400).some((s) => inBox(s.x, s.y, 1))) problems.push(`label of ${name(e)} touches line ${name(o)}`);
  }
  for (const o of edges) if (o !== e && o.box && !(b.x + b.w < o.box.x || o.box.x + o.box.w < b.x || b.y + b.h < o.box.y || o.box.y + o.box.h < b.y)) problems.push(`labels overlap: ${name(e)} / ${name(o)}`);
}
// 5. everything inside the canvas
for (const e of edges) for (const p of [e.p, e.q]) if (p.x < 2 || p.x > W - 2 || p.y < 2 || p.y > H - 2) problems.push(`${name(e)} leaves canvas`);
// 6. actors outside boundary
for (const a of Object.values(actors)) if (a.x > BOX.x1 - 20 && a.x < BOX.x2 + 20) problems.push(`actor ${a.label} not outside boundary`);
// 7. connectivity: every use case touched by an edge; every actor has an edge
const touched = new Set(); edges.forEach((e) => { touched.add(e.from); touched.add(e.to); });
for (const n of ns) if (!touched.has(n.id)) problems.push(`isolated use case "${n.label}"`);
for (const k of Object.keys(actors)) if (!touched.has(k)) problems.push(`isolated actor ${k}`);

const cnt = (t) => edges.filter((e) => e.type === t).length;
console.log(`use cases=${ns.length} relationships=${edges.length} (assoc=${cnt('assoc')}, include=${cnt('include')}, extend=${cnt('extend')}, generalization=${cnt('gen')})`);
console.log(problems.length ? 'PROBLEMS:\n' + problems.join('\n') : 'VALIDATION OK: no line through ovals, no crossings, no overlaps, all inside canvas');

// ---- SVG -------------------------------------------------------------------------
const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
let s = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" font-family="Arial, Helvetica, sans-serif">
<title>RJM Boardinghouse use case diagram</title>
<defs>
<marker id="open" markerUnits="userSpaceOnUse" markerWidth="12" markerHeight="12" refX="10" refY="6" orient="auto"><path d="M1,1 L10,6 L1,11" fill="none" stroke="#222" stroke-width="1.4"/></marker>
<marker id="tri" markerUnits="userSpaceOnUse" markerWidth="16" markerHeight="16" refX="14" refY="8" orient="auto"><path d="M1,1 L14,8 L1,15 z" fill="#fff" stroke="#222" stroke-width="1.6"/></marker>
</defs>
<rect width="${W}" height="${H}" fill="#fff"/>
<rect x="${BOX.x1}" y="${BOX.y1}" width="${BOX.x2 - BOX.x1}" height="${BOX.y2 - BOX.y1}" fill="none" stroke="#222" stroke-width="1.6"/>
<text x="${(BOX.x1 + BOX.x2) / 2}" y="${BOX.y1 + 30}" text-anchor="middle" font-size="19" font-weight="bold" fill="#111">RJM Boardinghouse Rent, Maintenance, and Security Management System</text>
`;
const line = (e, extra) => `<line x1="${e.p.x.toFixed(1)}" y1="${e.p.y.toFixed(1)}" x2="${e.q.x.toFixed(1)}" y2="${e.q.y.toFixed(1)}" ${extra}/>`;
s += '<g id="associations" stroke="#222" stroke-width="1.3" fill="none">\n';
for (const e of edges.filter((e) => e.type === 'assoc')) s += line(e, '') + '\n';
s += '</g>\n<g id="generalization">\n';
for (const e of edges.filter((e) => e.type === 'gen')) {
  s += line(e, 'stroke="#222" stroke-width="1.6" marker-end="url(#tri)"') + '\n';
  s += `<text x="${e.p.x + 8}" y="${(e.p.y + e.q.y) / 2}" font-size="11" font-style="italic" fill="#333">specializes</text>\n`;
}
s += '</g>\n<g id="include-extend" stroke="#222" stroke-width="1.2" stroke-dasharray="6 4" fill="none">\n';
for (const e of edges.filter((e) => LBL[e.type])) s += line(e, 'marker-end="url(#open)"') + '\n';
s += '</g>\n<g id="relationship-labels" font-size="10" font-style="italic" fill="#222" stroke="#fff" stroke-width="3" paint-order="stroke">\n';
for (const e of edges.filter((e) => e.box)) {
  const x = e.anchor === 'middle' ? e.box.x + e.box.w / 2 : e.box.x;
  s += `<text x="${x.toFixed(1)}" y="${(e.box.y + e.box.h - 3).toFixed(1)}" text-anchor="${e.anchor}">${esc(e.text)}</text>\n`;
}
s += '</g>\n<g id="use-cases">\n';
for (const n of ns) {
  const par = n.kind === 'parent';
  s += `<ellipse cx="${n.cx}" cy="${n.cy}" rx="${n.w / 2}" ry="${n.h / 2}" fill="${par ? '#eef3fb' : '#fff'}" stroke="#222" stroke-width="${par ? 2.2 : 1.3}"/>`;
  s += `<text x="${n.cx}" y="${n.cy + 4.5}" text-anchor="middle" font-size="${par ? 13 : 12}" ${par ? 'font-weight="bold"' : ''} fill="#111">${esc(n.label)}</text>\n`;
}
s += '</g>\n<g id="actors" stroke="#222" stroke-width="2" fill="#fff">\n';
for (const a of Object.values(actors)) {
  const { x, y } = a;
  s += `<circle cx="${x}" cy="${y - 30}" r="9"/><path d="M${x},${y - 21} V${y + 10} M${x - 17},${y - 12} H${x + 17} M${x},${y + 10} L${x - 14},${y + 36} M${x},${y + 10} L${x + 14},${y + 36}" fill="none"/>`;
  const parts = a.label.length > 12 ? a.label.split(' ') : [a.label]; // two short lines keep the name clear of the fanned lines
  s += `<text text-anchor="middle" font-size="14" font-weight="bold" stroke="none" fill="#111">${parts.map((t, i) => `<tspan x="${x}" y="${y + 56 + i * 16}">${esc(t)}</tspan>`).join('')}</text>\n`;
}
s += '</g>\n</svg>\n';
fs.writeFileSync(path.join(DIR, 'rjm-use-case-diagram.svg'), s);

// ---- draw.io -------------------------------------------------------------------------
let id = 10; const cid = {};
const cells = [];
cells.push(`<mxCell id="boundary" value="${esc('RJM Boardinghouse Rent, Maintenance, and Security Management System')}" style="rounded=0;whiteSpace=wrap;html=1;fillColor=none;verticalAlign=top;fontStyle=1;fontSize=16;" vertex="1" parent="1"><mxGeometry x="${BOX.x1}" y="${BOX.y1}" width="${BOX.x2 - BOX.x1}" height="${BOX.y2 - BOX.y1}" as="geometry"/></mxCell>`);
for (const [k, a] of Object.entries(actors)) {
  cid[k] = `a_${k}`;
  cells.push(`<mxCell id="${cid[k]}" value="${esc(a.label)}" style="shape=umlActor;verticalLabelPosition=bottom;verticalAlign=top;html=1;outlineConnect=0;fontStyle=1;fontSize=13;" vertex="1" parent="1"><mxGeometry x="${a.x - 15}" y="${a.y - 40}" width="30" height="60" as="geometry"/></mxCell>`);
}
for (const n of ns) {
  cid[n.id] = `u_${id++}`;
  const st = `ellipse;whiteSpace=wrap;html=1;fontSize=${n.kind === 'parent' ? 13 : 12};${n.kind === 'parent' ? 'fontStyle=1;strokeWidth=2;fillColor=#eef3fb;' : ''}`;
  cells.push(`<mxCell id="${cid[n.id]}" value="${esc(n.label)}" style="${st}" vertex="1" parent="1"><mxGeometry x="${n.cx - n.w / 2}" y="${n.cy - n.h / 2}" width="${n.w}" height="${n.h}" as="geometry"/></mxCell>`);
}
for (const e of edges) {
  const st = e.type === 'assoc' ? 'endArrow=none;html=1;'
    : e.type === 'gen' ? 'endArrow=block;endFill=0;endSize=12;html=1;'
    : 'endArrow=open;endFill=0;dashed=1;html=1;fontSize=10;fontStyle=2;labelBackgroundColor=#ffffff;';
  cells.push(`<mxCell id="e_${id++}" value="${LBL[e.type] ? esc(LBL[e.type]) : ''}" style="${st}" edge="1" parent="1" source="${cid[e.from]}" target="${cid[e.to]}"><mxGeometry relative="1" as="geometry"/></mxCell>`);
}
fs.writeFileSync(path.join(DIR, 'rjm-use-case-diagram.drawio'),
  `<mxfile><diagram name="RJM Use Case Diagram"><mxGraphModel dx="1200" dy="800" grid="0" page="0" pageScale="1" pageWidth="${W}" pageHeight="${H}"><root><mxCell id="0"/><mxCell id="1" parent="0"/>\n${cells.join('\n')}\n</root></mxGraphModel></diagram></mxfile>\n`);
