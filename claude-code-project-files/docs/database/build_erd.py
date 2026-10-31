#!/usr/bin/env python3
"""Build the RJM Boardinghouse ERD (erd.svg + erd.drawio) from schema-snapshot.sql.

Tables, columns, types, keys and foreign keys are parsed from the snapshot, never typed by hand.
Only the placement (LAYOUT) and the three application-level links (APP_LINKS) are authored here.
Regenerate the snapshot:  mysqldump --no-data --skip-comments --skip-triggers rjm_boardinghouse
Then:                     python build_erd.py   (and render erd.png from erd.svg, see README-ERD.md)
"""
import heapq, re
from html import escape
from pathlib import Path

HERE = Path(__file__).parent
G = 10                     # routing grid (px); every table x/y/w/h is a multiple of this
HDR, ROW = 30, 20          # header and row height
GUTTER, VGAP = 170, 80     # space between table columns / stacked tables
X0, Y0 = 60, 70

# ---------------------------------------------------------------- parse the schema snapshot
def parse(sql):
    tables = {}
    for m in re.finditer(r'CREATE TABLE `(\w+)` \((.*?)\n\) ENGINE', sql, re.S):
        t = dict(cols=[], pk=[], fks=[], uq=set(), checks=0)
        for line in m.group(2).split('\n'):
            line = line.strip().rstrip(',')
            if line.startswith('PRIMARY KEY'):
                t['pk'] = re.findall(r'`(\w+)`', line)
            elif line.startswith('UNIQUE KEY'):
                cols = re.findall(r'`(\w+)`', line)[1:]
                if len(cols) == 1:
                    t['uq'].add(cols[0])
            elif line.startswith('CONSTRAINT') and 'FOREIGN KEY' in line:
                f = re.match(r'CONSTRAINT `(\w+)` FOREIGN KEY \(`(\w+)`\) REFERENCES `(\w+)` \(`(\w+)`\)(.*)', line)
                t['fks'].append(dict(name=f[1], col=f[2], ref=f[3], refcol=f[4], rule=f[5].strip()))
            elif line.startswith('CONSTRAINT') and 'CHECK' in line:
                t['checks'] += 1
            elif line.startswith('`'):
                c = re.match(r'`(\w+)` (\w+)(\([^)]*\))?( unsigned)?(.*)', line)
                base, arg, uns = c[2], c[3] or '', bool(c[4])
                if base == 'enum':
                    typ = 'ENUM' + (arg if len(arg) <= 28 else '(...)')
                elif base in ('int', 'bigint'):
                    typ = base.upper() + (' UNSIGNED' if uns else '')
                elif base in ('varchar', 'char', 'decimal', 'tinyint'):
                    typ = base.upper() + arg
                else:
                    typ = base.upper()
                t['cols'].append(dict(name=c[1], type=typ, notnull='NOT NULL' in c[5]))
        tables[m.group(1)] = t
    for t in tables.values():
        for c in t['cols']:
            c['pk'] = c['name'] in t['pk']
            c['fk'] = any(f['col'] == c['name'] for f in t['fks'])
            c['uq'] = c['name'] in t['uq']
    return tables

# ---------------------------------------------------------------- authored: placement + app-level links
LAYOUT = [  # columns left -> right, tables top -> bottom
    ['room_types', 'rooms', 'electricity_charges', 'login_attempts', 'occupancy_snapshots', 'schema_migrations'],
    ['beds', 'boarder_profiles', 'electricity_bills', 'maintenance_requests', 'sos_alerts'],
    ['settings', 'audit_log', 'users', 'boarder_status_log', 'notifications'],
    ['payments', 'payment_allocations', 'penalties', 'penalty_rules'],
    ['rent_charges', 'expenses', 'inquiries', 'incidents'],
]
COL_Y = [0, 0, 0, 0, 0]   # optional per-column y offsets (tuned after looking at the render)
# Relationships that exist only in application code (no FOREIGN KEY): child.col -> parent.col
APP_LINKS = [
    ('payment_allocations', 'reference_id', 'penalties', 'id'),
    ('payment_allocations', 'reference_id', 'electricity_charges', 'id'),
    ('payment_allocations', 'reference_id', 'rent_charges', 'period'),
]
PALETTE = {'users': '#34495e', 'rooms': '#2e8b57', 'room_types': '#6aa84f', 'beds': '#1b9aaa', 'payments': '#e67e22',
           'penalties': '#c0392b', 'penalty_rules': '#a93226', 'electricity_bills': '#8e44ad', 'boarder_profiles': '#2c7fb8'}

# ---------------------------------------------------------------- geometry
def size_tables(T):
    for n, t in T.items():
        name_w = max(len(c['name']) for c in t['cols']) * 7.2
        type_w = max(len(c['type']) for c in t['cols']) * 7.2
        w = max(34 + name_w + 34 + type_w + 18, len(n) * 8.6 + 60, 250)
        t['w'] = int(-(-w // G) * G)
        t['h'] = HDR + ROW * len(t['cols']) + 10

def place(T):
    x = X0
    for ci, col in enumerate(LAYOUT):
        cw = max(T[n]['w'] for n in col)
        y = Y0 + COL_Y[ci]
        for n in col:
            T[n]['x'], T[n]['y'] = x, y
            y += T[n]['h'] + VGAP
        x += cw + GUTTER
    W = x - GUTTER + X0
    H = max(T[n]['y'] + T[n]['h'] for n in T) + 70
    return W, H

def row_cy(t, name):
    i = [c['name'] for c in t['cols']].index(name)
    return t['y'] + HDR + ROW * i + ROW // 2

# ---------------------------------------------------------------- orthogonal router (A* on a coarse grid, turn + overlap penalties)
class Router:
    DIRS = [(1, 0), (-1, 0), (0, 1), (0, -1)]

    def __init__(self, W, H, T):
        self.gw, self.gh = W // G + 2, H // G + 2
        self.block = set()
        for t in T.values():
            for gx in range((t['x'] - 12) // G, (t['x'] + t['w'] + 12) // G + 1):
                for gy in range((t['y'] - 12) // G, (t['y'] + t['h'] + 12) // G + 1):
                    self.block.add((gx, gy))
        self.used = {}   # cell -> set('h'/'v')

    def route(self, a, b, da, db):
        """a, b: stub-end points (px); da/db: outward directions at a/b. The path must arrive at b moving against db."""
        start = (a[0] // G, a[1] // G); goal = (b[0] // G, b[1] // G)
        arrive = self.DIRS.index((-db[0], -db[1]))
        n = 0
        pq = [(0, 0, start, self.DIRS.index(da))]
        best = {(start, self.DIRS.index(da)): 0}; prev = {}
        while pq:
            cost, _, cell, d = heapq.heappop(pq)
            if best.get((cell, d), 1e18) < cost:
                continue
            if cell == goal and d == arrive:
                path = [cell]; st = (cell, d)
                while st in prev:
                    st = prev[st]; path.append(st[0])
                return [(c[0] * G, c[1] * G) for c in reversed(path)]
            for nd, (dx, dy) in enumerate(self.DIRS):
                if (dx, dy) == (-self.DIRS[d][0], -self.DIRS[d][1]):
                    continue
                nc = (cell[0] + dx, cell[1] + dy)
                if not (0 <= nc[0] < self.gw and 0 <= nc[1] < self.gh):
                    continue
                if nc in self.block and nc != goal and nc != start:
                    continue
                c = cost + 1 + (6 if nd != d else 0)
                u = self.used.get(nc)
                if u:
                    c += 40 if ('h' if dy == 0 else 'v') in u else 5
                if best.get((nc, nd), 1e18) > c:
                    best[(nc, nd)] = c; prev[(nc, nd)] = (cell, d); n += 1
                    heapq.heappush(pq, (c, n, nc, nd))
        raise RuntimeError(f'no route {a}->{b}')

    def mark(self, pts):
        for p, q in zip(pts, pts[1:]):
            o = 'h' if p[1] == q[1] else 'v'
            sx = (q[0] > p[0]) - (q[0] < p[0]); sy = (q[1] > p[1]) - (q[1] < p[1])
            for i in range(max(abs(q[0] - p[0]), abs(q[1] - p[1])) // G + 1):
                self.used.setdefault(((p[0] + sx * i * G) // G, (p[1] + sy * i * G) // G), set()).add(o)

def simplify(pts):
    out = []
    for p in pts:
        if not out or out[-1] != p:
            out.append(p)
    i = 1
    while i < len(out) - 1:
        a, b, c = out[i - 1], out[i], out[i + 1]
        if a[0] == b[0] == c[0] or a[1] == b[1] == c[1]:
            del out[i]
        else:
            i += 1
    return out

def build_edges(T, W, H):
    edges = []
    for cn, t in T.items():
        for f in t['fks']:
            col = next(c for c in t['cols'] if c['name'] == f['col'])
            edges.append(dict(child=cn, ccol=f['col'], parent=f['ref'], pcol=f['refcol'], app=False,
                              optional=not col['notnull'], one=(col['pk'] or col['uq']), rule=f['rule']))
    for cn, cc, pn, pc in APP_LINKS:
        edges.append(dict(child=cn, ccol=cc, parent=pn, pcol=pc, app=True, optional=True, one=False, rule=''))
    cx = lambda t: t['x'] + t['w'] / 2
    for e in edges:
        c, p = T[e['child']], T[e['parent']]
        if e['child'] == e['parent'] or cx(c) == cx(p):
            e['cside'] = e['pside'] = 'R'
        elif cx(p) > cx(c):
            e['cside'], e['pside'] = 'R', 'L'
        else:
            e['cside'], e['pside'] = 'L', 'R'
        e['cy'] = row_cy(c, e['ccol'])
    groups = {}
    for e in edges:
        groups.setdefault((e['parent'], e['pside']), []).append(e)
    for (pn, side), es in groups.items():       # distinct landing slots on each parent side, in child order
        p = T[pn]; body = p['h'] - HDR - 10
        step = ROW if len(es) * ROW <= body else G
        es.sort(key=lambda e: (e['cy'], e['ccol']))
        for k, e in enumerate(es):
            e['py'] = p['y'] + HDR + 10 + k * step
    R = Router(W, H, T); STUB = 20
    for e in sorted(edges, key=lambda e: abs(T[e['child']]['x'] - T[e['parent']]['x']) + abs(e['cy'] - e['py'])):
        c, p = T[e['child']], T[e['parent']]
        e['a'] = (c['x'] + (c['w'] if e['cside'] == 'R' else 0), e['cy'])
        e['b'] = (p['x'] + (p['w'] if e['pside'] == 'R' else 0), e['py'])
        e['da'] = (1, 0) if e['cside'] == 'R' else (-1, 0)
        e['db'] = (1, 0) if e['pside'] == 'R' else (-1, 0)
        sa = (e['a'][0] + e['da'][0] * STUB, e['a'][1]); sb = (e['b'][0] + e['db'][0] * STUB, e['b'][1])
        pts = simplify([e['a'], sa] + R.route(sa, sb, e['da'], e['db']) + [sb, e['b']])
        R.mark(pts)
        e['pts'] = pts
    return edges

# ---------------------------------------------------------------- SVG
FONT = 'Segoe UI, Arial, sans-serif'
MONO = 'Consolas, monospace'

def diamond(cx, cy, fill, stroke, r=4.6):
    return f'<path d="M{cx},{cy-r} L{cx+r},{cy} L{cx},{cy+r} L{cx-r},{cy} Z" fill="{fill}" stroke="{stroke}" stroke-width="1"/>'

def key_icon(cx, cy):
    return (f'<circle cx="{cx-2.5}" cy="{cy-2.5}" r="3.4" fill="#f2c200" stroke="#9a7b00"/>'
            f'<path d="M{cx-0.5},{cy-0.5} L{cx+5},{cy+5} M{cx+2.5},{cy+2.5} L{cx+4.5},{cy+0.5} M{cx+4},{cy+4} L{cx+6},{cy+2}" stroke="#9a7b00" stroke-width="1.6" fill="none"/>')

def svg_table(n, t):
    x, y, w, h = t['x'], t['y'], t['w'], t['h']
    s = [f'<g id="tbl_{n}">',
         f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="3" fill="#fff" stroke="#7d7d7d" stroke-width="1.2"/>',
         f'<path d="M{x},{y+HDR} V{y+3} Q{x},{y} {x+3},{y} H{x+w-3} Q{x+w},{y} {x+w},{y+3} V{y+HDR} Z" fill="#f5e08a" stroke="#7d7d7d" stroke-width="1.2"/>',
         f'<rect x="{x+9}" y="{y+9}" width="12" height="12" fill="#fff" stroke="#5b6b7a"/><path d="M{x+9},{y+14} H{x+21} M{x+14},{y+9} V{y+21}" stroke="#5b6b7a"/>',
         f'<text x="{x+30}" y="{y+20}" font-family="{FONT}" font-size="14" font-weight="700" fill="#222">{n}</text>']
    for i, c in enumerate(t['cols']):
        cy = y + HDR + ROW * i + ROW // 2
        if i % 2:
            s.append(f'<rect x="{x+1}" y="{cy-ROW//2}" width="{w-2}" height="{ROW}" fill="#f7f9fb"/>')
        ix = x + 16
        if c['pk']:
            s.append(key_icon(ix, cy))
            if c['fk']:
                s.append(diamond(ix + 12, cy, '#c0392b', '#7b241c', 4))
        elif c['fk']:
            s.append(diamond(ix, cy, '#c0392b' if c['notnull'] else '#fff', '#c0392b'))
        else:
            s.append(diamond(ix, cy, '#2a9d8f' if c['notnull'] else '#fff', '#2a9d8f'))
        nx = x + 34 + (8 if c['pk'] and c['fk'] else 0)
        s.append(f'<text x="{nx}" y="{cy+4}" font-family="{MONO}" font-size="12" font-weight="{700 if c["pk"] else 400}" fill="#222">{c["name"]}</text>')
        if c['uq']:
            s.append(f'<text x="{x+w-10-len(c["type"])*6.6-8}" y="{cy+4}" text-anchor="end" font-family="{MONO}" font-size="10" font-weight="700" fill="#2a9d8f">UQ</text>')
        s.append(f'<text x="{x+w-10}" y="{cy+4}" text-anchor="end" font-family="{MONO}" font-size="11" fill="#5f6b76">{escape(c["type"])}</text>')
    s.append('</g>')
    return '\n'.join(s)

def end_symbols(pt, d, kind, color):
    """Cardinality symbol at pt; d = outward unit vector. kinds: many0 | one01 (zero-or-one) | one1 (exactly one)"""
    x, y = pt; dx = d[0]
    ln = lambda x1, y1, x2, y2: f'<path d="M{x+dx*x1},{y+y1} L{x+dx*x2},{y+y2}" stroke="{color}" stroke-width="1.6" fill="none"/>'
    circ = lambda off: f'<circle cx="{x+dx*off}" cy="{y}" r="3.6" fill="#fff" stroke="{color}" stroke-width="1.6"/>'
    if kind == 'many0':
        return ln(0, -6, 11, 0) + ln(0, 6, 11, 0) + ln(0, 0, 11, 0) + circ(16)
    if kind == 'one01':
        return ln(7, -6, 7, 6) + circ(14)
    return ln(7, -6, 7, 6) + ln(12, -6, 12, 6)

def svg_edge(e):
    col = '#8a8f98' if e['app'] else PALETTE.get(e['parent'], '#34495e')
    dash = ' stroke-dasharray="7 5"' if e['app'] else ''
    pts = ' '.join(f'{x},{y}' for x, y in e['pts'])
    return (f'<g class="rel" data-child="{e["child"]}.{e["ccol"]}" data-parent="{e["parent"]}.{e["pcol"]}">'
            f'<polyline points="{pts}" fill="none" stroke="{col}" stroke-width="1.5"{dash} stroke-linejoin="round"/>'
            + end_symbols(e['a'], e['da'], 'one01' if e['one'] else 'many0', col)
            + end_symbols(e['b'], e['db'], 'one01' if e['optional'] else 'one1', col) + '</g>')

def svg_legend(W, H, n_fk):
    x, y = X0, H - 215
    txt = lambda tx, ty, s, size=12, extra='': f'<text x="{tx}" y="{ty}" font-family="{FONT}" font-size="{size}" fill="#333" {extra}>{escape(s)}</text>'
    s = [f'<g id="legend"><rect x="{x}" y="{y}" width="930" height="165" rx="4" fill="#fff" stroke="#9aa5b1"/>',
         txt(x + 14, y + 22, 'Legend', 13, 'font-weight="700"')]
    items = [(key_icon(x + 22, y + 46), 'Primary key (bold name)'),
             (diamond(x + 22, y + 70, '#c0392b', '#c0392b'), 'Foreign key, NOT NULL'),
             (diamond(x + 22, y + 94, '#fff', '#c0392b'), 'Foreign key, nullable'),
             (diamond(x + 22, y + 118, '#2a9d8f', '#2a9d8f'), 'Column, NOT NULL'),
             (diamond(x + 22, y + 142, '#fff', '#2a9d8f'), 'Column, nullable')]
    for k, (icon, label) in enumerate(items):
        s += [icon, txt(x + 40, y + 50 + k * 24, label)]
    gx = x + 250
    def sample(py, kc, kp, dash, label, color='#34495e'):
        return (f'<polyline points="{gx},{py} {gx+110},{py}" stroke="{color}" stroke-width="1.5"{dash}/>'
                + end_symbols((gx, py), (1, 0), kc, color) + end_symbols((gx + 110, py), (-1, 0), kp, color)
                + txt(gx + 130, py + 4, label))
    s += [sample(y + 46, 'many0', 'one1', '', 'one-to-many, parent required (FK NOT NULL)'),
          sample(y + 70, 'many0', 'one01', '', 'one-to-many, parent optional (FK nullable)'),
          sample(y + 94, 'one01', 'one1', '', 'one-to-one / zero-or-one (FK is the PRIMARY KEY or UNIQUE)'),
          sample(y + 118, 'many0', 'one01', ' stroke-dasharray="7 5"', 'application-level link only (no FOREIGN KEY in the database)', '#8a8f98'),
          txt(gx, y + 146, f'UQ = single-column UNIQUE key. Line colour = referenced (parent) table. Solid lines = the {n_fk} enforced FOREIGN KEYs.', 11),
          '</g>']
    nx = x + 960
    notes = ['Relationships that exist in application code only (not enforced by the database):',
             '• payment_allocations.reference_id is polymorphic: penalties.id, electricity_charges.id,',
             '   or the rent cycle (rent_charges.period, together with boarder_id), by allocation_type.',
             '• notifications and audit_log carry entity_type + entity_id pointing at any entity;',
             '   login_attempts.email matches users.email by value only.',
             '• payments and penalties reference each other (target_penalty_id / paid_payment_id).',
             'Standalone tables (no relationships): occupancy_snapshots, schema_migrations.']
    s.append(f'<g id="notes"><rect x="{nx}" y="{y}" width="840" height="165" rx="4" fill="#fffdf2" stroke="#c9b458"/>')
    for k, line in enumerate(notes):
        s.append(txt(nx + 14, y + 24 + k * 21, line, 12, 'font-weight="700"' if k == 0 else ''))
    s.append('</g>')
    return '\n'.join(s)

def write_svg(T, edges, W, H, n_fk):
    out = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}">',
           f'<rect width="{W}" height="{H}" fill="#ffffff"/>']
    out += [svg_edge(e) for e in edges]
    out += [svg_table(n, t) for n, t in T.items()]
    out += ['</svg>']   # title and legend removed on request (svg_legend() kept for reference)
    (HERE / 'erd.svg').write_text('\n'.join(out), encoding='utf-8')

# ---------------------------------------------------------------- draw.io (each table and each relationship is its own editable object)
def write_drawio(T, edges, W, H):
    ids = {}; cells = []; k = [1]
    def nxt():
        k[0] += 1; return f'c{k[0]}'
    for n, t in T.items():
        tid = ids[n] = nxt()
        cells.append(f'<mxCell id="{tid}" value="{n}" style="swimlane;fontStyle=1;childLayout=none;horizontal=1;startSize={HDR};fillColor=#f5e08a;strokeColor=#7d7d7d;swimlaneFillColor=#ffffff;fontSize=14;align=left;spacingLeft=30;" vertex="1" parent="1">'
                     f'<mxGeometry x="{t["x"]}" y="{t["y"]}" width="{t["w"]}" height="{t["h"]}" as="geometry"/></mxCell>')
        lw = int(t['w'] * 0.58)
        for i, c in enumerate(t['cols']):
            badge = ('PK ' if c['pk'] else '') + ('FK ' if c['fk'] else '') + ('' if c['notnull'] else '(null) ')
            lid, rid = nxt(), nxt()
            ids[(n, c['name'], 'L')], ids[(n, c['name'], 'R')] = lid, rid
            colr = 'c0392b' if c['fk'] else '222222'
            cells.append(f'<mxCell id="{lid}" value="{escape(badge + c["name"])}" style="text;align=left;verticalAlign=middle;spacingLeft=8;fontFamily=Consolas;fontSize=12;fontStyle={1 if c["pk"] else 0};fontColor=#{colr};strokeColor=none;fillColor=none;" vertex="1" parent="{tid}">'
                         f'<mxGeometry y="{HDR + i*ROW}" width="{lw}" height="{ROW}" as="geometry"/></mxCell>')
            cells.append(f'<mxCell id="{rid}" value="{escape(c["type"] + ("  UQ" if c["uq"] else ""))}" style="text;align=right;verticalAlign=middle;spacingRight=8;fontFamily=Consolas;fontSize=11;fontColor=#5f6b76;strokeColor=none;fillColor=none;" vertex="1" parent="{tid}">'
                         f'<mxGeometry x="{lw}" y="{HDR + i*ROW}" width="{t["w"]-lw}" height="{ROW}" as="geometry"/></mxCell>')
    for e in edges:
        p = T[e['parent']]
        src = ids[(e['child'], e['ccol'], 'R' if e['cside'] == 'R' else 'L')]
        col = '#8a8f98' if e['app'] else PALETTE.get(e['parent'], '#34495e')
        sa = 'ERzeroToOne' if e['one'] else 'ERzeroToMany'
        ea = 'ERzeroToOne' if e['optional'] else 'ERmandOne'
        wp = ''.join(f'<mxPoint x="{x}" y="{y}"/>' for x, y in e['pts'][1:-1])
        cells.append(f'<mxCell id="{nxt()}" value="" style="edgeStyle=none;rounded=0;html=1;startArrow={sa};endArrow={ea};startFill=0;endFill=0;strokeColor={col};strokeWidth=1.5;{"dashed=1;" if e["app"] else ""}'
                     f'exitX={1 if e["cside"] == "R" else 0};exitY=0.5;exitPerimeter=0;entryX={1 if e["pside"] == "R" else 0};entryY={round((e["py"] - p["y"]) / p["h"], 4)};entryPerimeter=0;" '
                     f'edge="1" parent="1" source="{src}" target="{ids[e["parent"]]}">'
                     f'<mxGeometry relative="1" as="geometry"><Array as="points">{wp}</Array></mxGeometry></mxCell>')
    xml = ('<mxfile host="app.diagrams.net"><diagram name="RJM Boardinghouse ERD" id="erd">'
           f'<mxGraphModel dx="{W}" dy="{H}" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="{W}" pageHeight="{H}" math="0" shadow="0">'
           '<root><mxCell id="0"/><mxCell id="1" parent="0"/>' + ''.join(cells) + '</root></mxGraphModel></diagram></mxfile>')
    (HERE / 'erd.drawio').write_text(xml, encoding='utf-8')

def main():
    T = parse((HERE / 'schema-snapshot.sql').read_text(encoding='utf-8'))
    placed = {n for col in LAYOUT for n in col}
    assert placed == set(T), f'layout/schema mismatch: {placed ^ set(T)}'
    size_tables(T)
    W, H = place(T)
    W, H = -(-W // G) * G, -(-H // G) * G
    edges = build_edges(T, W, H)
    n_fk = sum(len(t['fks']) for t in T.values())
    write_svg(T, edges, W, H, n_fk)
    write_drawio(T, edges, W, H)
    print(f'{len(T)} tables, {n_fk} FKs, {len(edges) - n_fk} app links, canvas {W}x{H}')

if __name__ == '__main__':
    main()
