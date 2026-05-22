/**
 * game_network.js — Interactive Relationship Network Graph
 * Pure SVG + Vanilla JS. No external dependencies.
 *
 * Reads:  window.__PJ_NETWORK_DATA = { relations: [...], groups: [...] }
 * Target: #pj-network-container
 */
(function() {
  'use strict';

  var NS = 'http://www.w3.org/2000/svg';

  // ==================== CONFIG ====================
  var CFG = {
    nodeRadius: 26,
    hullPadding: 52,
    labelYOffset: 42,
    // Force simulation
    repulsion: 4500,
    groupAttraction: 0.03,
    gravity: 0.008,
    damping: 0.82,
    iterations: 300,
    minDist: 90,
    // Visual
    defaultNodeColor: '#6366f1',
    linkOpacity: 0.25,
    hullFillOpacity: 0.07,
    hullStrokeOpacity: 0.4,
    dimmedOpacity: 0.12
  };

  // Tag → color map (matches personaje.php)
  var TAG_COLORS = {
    'Amigo':'#10b981','Compañero':'#3b82f6','Aliado':'#3b82f6',
    'Rival':'#f59e0b','Enemigo':'#ef4444','Némesis':'#ef4444',
    'Familiar':'#ec4899','Hermano':'#ec4899','Hermana':'#ec4899',
    'Padre':'#8b5cf6','Madre':'#8b5cf6',
    'Maestro':'#f97316','Mentor':'#f97316',
    'Aprendiz':'#06b6d4','Protegido':'#06b6d4',
    'Interés Romántico':'#ec4899','Cónyuge':'#ec4899','Amante':'#ec4899',
    'Conocido':'#6b7280','Socio':'#8b5cf6','Cómplice':'#8b5cf6',
    'Subordinado':'#64748b','Superior':'#64748b',
    'Adversario':'#f59e0b','Seguidor':'#06b6d4','Líder':'#f97316','Miembro':'#6b7280'
  };

  // ==================== UTILITIES ====================
  function svgEl(tag, attrs, parent) {
    var e = document.createElementNS(NS, tag);
    if (attrs) {
      for (var k in attrs) {
        if (attrs.hasOwnProperty(k)) e.setAttribute(k, attrs[k]);
      }
    }
    if (parent) parent.appendChild(e);
    return e;
  }

  function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }

  function truncate(s, max) {
    return s.length > max ? s.substring(0, max - 1) + '\u2026' : s;
  }

  function findNode(nodes, id) {
    for (var i = 0; i < nodes.length; i++) {
      if (nodes[i].id === id) return nodes[i];
    }
    return null;
  }

  function nodeColor(node) {
    if (node.tags && node.tags.length > 0 && TAG_COLORS[node.tags[0]]) {
      return TAG_COLORS[node.tags[0]];
    }
    return CFG.defaultNodeColor;
  }

  // ==================== FORCE SIMULATION ====================
  function simulate(nodes, groups, W, H) {
    var n = nodes.length;
    var cx = W / 2, cy = H / 2;

    // Init positions in a circle
    for (var i = 0; i < n; i++) {
      var angle = (2 * Math.PI * i) / n;
      var r = Math.min(W, H) * 0.28;
      nodes[i].x = cx + Math.cos(angle) * r;
      nodes[i].y = cy + Math.sin(angle) * r;
      nodes[i].vx = 0;
      nodes[i].vy = 0;
    }

    // Node index lookup
    var nIdx = {};
    for (var i = 0; i < n; i++) nIdx[nodes[i].id] = i;

    for (var iter = 0; iter < CFG.iterations; iter++) {
      var alpha = 1 - iter / CFG.iterations;

      // Repulsion (all pairs)
      for (var i = 0; i < n; i++) {
        for (var j = i + 1; j < n; j++) {
          var dx = nodes[j].x - nodes[i].x;
          var dy = nodes[j].y - nodes[i].y;
          var d = Math.sqrt(dx * dx + dy * dy) || 1;
          var f = CFG.repulsion * alpha / (d * d);
          var fx = f * dx / d, fy = f * dy / d;
          nodes[i].vx -= fx; nodes[i].vy -= fy;
          nodes[j].vx += fx; nodes[j].vy += fy;
        }
      }

      // Group attraction
      for (var g = 0; g < groups.length; g++) {
        var mem = groups[g].members;
        for (var a = 0; a < mem.length; a++) {
          for (var b = a + 1; b < mem.length; b++) {
            var ai = nIdx[mem[a]], bi = nIdx[mem[b]];
            if (ai === undefined || bi === undefined) continue;
            var dx = nodes[bi].x - nodes[ai].x;
            var dy = nodes[bi].y - nodes[ai].y;
            var d = Math.sqrt(dx * dx + dy * dy) || 1;
            var f = CFG.groupAttraction * d * alpha;
            var fx = f * dx / d, fy = f * dy / d;
            nodes[ai].vx += fx; nodes[ai].vy += fy;
            nodes[bi].vx -= fx; nodes[bi].vy -= fy;
          }
        }
      }

      // Gravity
      for (var i = 0; i < n; i++) {
        nodes[i].vx += (cx - nodes[i].x) * CFG.gravity * alpha;
        nodes[i].vy += (cy - nodes[i].y) * CFG.gravity * alpha;
      }

      // Damping + apply
      var pad = CFG.hullPadding + CFG.nodeRadius + 10;
      for (var i = 0; i < n; i++) {
        nodes[i].vx *= CFG.damping;
        nodes[i].vy *= CFG.damping;
        nodes[i].x += nodes[i].vx;
        nodes[i].y += nodes[i].vy;
        nodes[i].x = clamp(nodes[i].x, pad, W - pad);
        nodes[i].y = clamp(nodes[i].y, pad, H - pad);
      }

      // Collision
      for (var i = 0; i < n; i++) {
        for (var j = i + 1; j < n; j++) {
          var dx = nodes[j].x - nodes[i].x;
          var dy = nodes[j].y - nodes[i].y;
          var d = Math.sqrt(dx * dx + dy * dy) || 1;
          if (d < CFG.minDist) {
            var push = (CFG.minDist - d) / 2;
            var px = push * dx / d, py = push * dy / d;
            nodes[i].x -= px; nodes[i].y -= py;
            nodes[j].x += px; nodes[j].y += py;
          }
        }
      }
    }
  }

  // ==================== CONVEX HULL (Graham Scan) ====================
  function cross(O, A, B) {
    return (A.x - O.x) * (B.y - O.y) - (A.y - O.y) * (B.x - O.x);
  }

  function grahamScan(pts) {
    if (pts.length < 3) return pts.slice();
    var sorted = pts.slice().sort(function(a, b) {
      return a.x === b.x ? a.y - b.y : a.x - b.x;
    });
    var lower = [];
    for (var i = 0; i < sorted.length; i++) {
      while (lower.length >= 2 && cross(lower[lower.length-2], lower[lower.length-1], sorted[i]) <= 0)
        lower.pop();
      lower.push(sorted[i]);
    }
    var upper = [];
    for (var i = sorted.length - 1; i >= 0; i--) {
      while (upper.length >= 2 && cross(upper[upper.length-2], upper[upper.length-1], sorted[i]) <= 0)
        upper.pop();
      upper.push(sorted[i]);
    }
    lower.pop(); upper.pop();
    return lower.concat(upper);
  }

  // ==================== HULL PATH ====================
  function circlePath(cx, cy, r) {
    return 'M '+(cx-r)+','+cy+' a '+r+','+r+' 0 1,0 '+(r*2)+',0'+
           ' a '+r+','+r+' 0 1,0 '+(-r*2)+',0 Z';
  }

  function hullPath(positions, padding) {
    if (!positions.length) return '';
    if (positions.length === 1) return circlePath(positions[0].x, positions[0].y, padding);

    var pts;
    if (positions.length === 2) {
      // Create 4-point diamond for 2-member groups → capsule shape
      var p1 = positions[0], p2 = positions[1];
      var dx = p2.x - p1.x, dy = p2.y - p1.y;
      var d = Math.sqrt(dx*dx + dy*dy) || 1;
      var nx = -dy/d, ny = dx/d;
      var w = padding * 0.45;
      pts = [
        {x: p1.x + nx*w, y: p1.y + ny*w},
        {x: p1.x - nx*w, y: p1.y - ny*w},
        {x: p2.x + nx*w, y: p2.y + ny*w},
        {x: p2.x - nx*w, y: p2.y - ny*w}
      ];
    } else {
      pts = positions;
    }

    var hull = grahamScan(pts);

    // Centroid
    var cx = 0, cy = 0;
    for (var i = 0; i < hull.length; i++) { cx += hull[i].x; cy += hull[i].y; }
    cx /= hull.length; cy /= hull.length;

    // Expand outward from centroid
    var exp = [];
    for (var i = 0; i < hull.length; i++) {
      var dx = hull[i].x - cx, dy = hull[i].y - cy;
      var d = Math.sqrt(dx*dx + dy*dy) || 1;
      exp.push({x: hull[i].x + dx/d * padding, y: hull[i].y + dy/d * padding});
    }

    return closedSpline(exp);
  }

  function closedSpline(pts) {
    var n = pts.length;
    if (n < 3) {
      var d = 'M '+pts[0].x.toFixed(1)+','+pts[0].y.toFixed(1);
      for (var i = 1; i < n; i++) d += ' L '+pts[i].x.toFixed(1)+','+pts[i].y.toFixed(1);
      return d + ' Z';
    }
    // Closed Catmull-Rom → cubic bezier
    var d = 'M '+pts[0].x.toFixed(1)+','+pts[0].y.toFixed(1);
    for (var i = 0; i < n; i++) {
      var p0 = pts[(i-1+n)%n], p1 = pts[i], p2 = pts[(i+1)%n], p3 = pts[(i+2)%n];
      var c1x = p1.x + (p2.x - p0.x)/6, c1y = p1.y + (p2.y - p0.y)/6;
      var c2x = p2.x - (p3.x - p1.x)/6, c2y = p2.y - (p3.y - p1.y)/6;
      d += ' C '+c1x.toFixed(1)+','+c1y.toFixed(1)+' '+c2x.toFixed(1)+','+c2y.toFixed(1)+' '+p2.x.toFixed(1)+','+p2.y.toFixed(1);
    }
    return d + ' Z';
  }

  // ==================== RENDERING ====================
  function buildGraph(container, data) {
    var rect = container.getBoundingClientRect();
    var W = rect.width || 800, H = 500;

    // Build nodes
    var nodes = [];
    for (var i = 0; i < data.relations.length; i++) {
      var r = data.relations[i];
      nodes.push({
        id: r.id, name: r.name || '?', image: r.image || '',
        pjId: r.pj_id || null, isNpc: !!r.is_npc,
        tags: r.tags || [], x: 0, y: 0, vx: 0, vy: 0
      });
    }
    var groups = data.groups || [];
    if (!nodes.length) return null;

    simulate(nodes, groups, W, H);

    // Create SVG
    container.innerHTML = '';
    var svg = svgEl('svg', {
      width: '100%', height: H,
      viewBox: '0 0 '+W+' '+H,
      style: 'cursor:grab; display:block;'
    }, container);

    var state = {
      svg: svg, nodes: nodes, groups: groups, W: W, H: H,
      viewBox: {x:0, y:0, w:W, h:H},
      dragging: null, panning: false, panStart: null,
      dragMoved: false
    };

    // Defs: clip paths + dot pattern
    var defs = svgEl('defs', {}, svg);
    for (var i = 0; i < nodes.length; i++) {
      var cp = svgEl('clipPath', {id: 'clip-n-'+i}, defs);
      svgEl('circle', {cx:0, cy:0, r: CFG.nodeRadius - 2}, cp);
    }
    var pattern = svgEl('pattern', {
      id:'net-dots', width:20, height:20, patternUnits:'userSpaceOnUse'
    }, defs);
    svgEl('circle', {cx:10, cy:10, r:0.7, fill:'rgba(255,255,255,0.05)'}, pattern);

    // Background
    svgEl('rect', {
      x:-5000, y:-5000, width:10000, height:10000,
      fill:'url(#net-dots)', 'class':'net-bg'
    }, svg);

    // Layers
    state.gHulls = svgEl('g', {'class':'net-layer-hulls'}, svg);
    state.gLinks = svgEl('g', {'class':'net-layer-links'}, svg);
    state.gNodes = svgEl('g', {'class':'net-layer-nodes'}, svg);

    renderAll(state);
    setupInteraction(state);
    return state;
  }

  function renderAll(s) { renderHulls(s); renderLinks(s); renderNodes(s); }

  function renderHulls(s) {
    s.gHulls.innerHTML = '';
    for (var g = 0; g < s.groups.length; g++) {
      var grp = s.groups[g], positions = [];
      for (var m = 0; m < grp.members.length; m++) {
        var nd = findNode(s.nodes, grp.members[m]);
        if (nd) positions.push({x: nd.x, y: nd.y});
      }
      if (!positions.length) continue;

      var path = hullPath(positions, CFG.hullPadding);
      svgEl('path', {
        d: path, fill: grp.color, 'fill-opacity': CFG.hullFillOpacity,
        stroke: grp.color, 'stroke-opacity': CFG.hullStrokeOpacity,
        'stroke-width': 2, 'stroke-dasharray': '8 4',
        'class': 'net-hull', 'data-group': g
      }, s.gHulls);

      // Group label at top of hull
      var cx2 = 0, minY = Infinity;
      for (var i = 0; i < positions.length; i++) {
        cx2 += positions[i].x;
        if (positions[i].y < minY) minY = positions[i].y;
      }
      cx2 /= positions.length;

      svgEl('text', {
        x: cx2, y: minY - CFG.hullPadding - 6,
        fill: grp.color, 'font-size': '11', 'font-weight': '700',
        'text-anchor': 'middle', 'letter-spacing': '1.5',
        'pointer-events': 'all', 'class': 'net-group-label', 'data-group': g,
        style: 'font-family:Inter,sans-serif; text-transform:uppercase; cursor:pointer;'
      }, s.gHulls).textContent = grp.name;
    }
  }

  function renderLinks(s) {
    s.gLinks.innerHTML = '';
    for (var g = 0; g < s.groups.length; g++) {
      var grp = s.groups[g], color = grp.color;
      for (var a = 0; a < grp.members.length; a++) {
        for (var b = a + 1; b < grp.members.length; b++) {
          var na = findNode(s.nodes, grp.members[a]);
          var nb = findNode(s.nodes, grp.members[b]);
          if (!na || !nb) continue;
          svgEl('line', {
            x1: na.x, y1: na.y, x2: nb.x, y2: nb.y,
            stroke: color, 'stroke-width': 1.5, 'stroke-opacity': CFG.linkOpacity,
            'class': 'net-link', 'data-group': g
          }, s.gLinks);
        }
      }
    }
  }

  function renderNodes(s) {
    s.gNodes.innerHTML = '';
    for (var i = 0; i < s.nodes.length; i++) {
      var nd = s.nodes[i];
      var color = nodeColor(nd);
      var g = svgEl('g', {
        'class': 'net-node', 'data-node-idx': i,
        transform: 'translate('+nd.x.toFixed(1)+','+nd.y.toFixed(1)+')',
        style: 'cursor:pointer;'
      }, s.gNodes);

      // Glow ring
      svgEl('circle', {
        cx:0, cy:0, r: CFG.nodeRadius + 4,
        fill:'none', stroke: color, 'stroke-width': 1.5, 'stroke-opacity': 0.2
      }, g);
      // Base circle
      svgEl('circle', {
        cx:0, cy:0, r: CFG.nodeRadius,
        fill:'#16162a', stroke: color, 'stroke-width': 2.5
      }, g);
      // Avatar image
      if (nd.image) {
        svgEl('image', {
          href: nd.image,
          x: -(CFG.nodeRadius - 2), y: -(CFG.nodeRadius - 2),
          width: (CFG.nodeRadius - 2)*2, height: (CFG.nodeRadius - 2)*2,
          'clip-path': 'url(#clip-n-'+i+')',
          preserveAspectRatio: 'xMidYMid slice'
        }, g);
      }
      // Name label
      var txt = svgEl('text', {
        x: 0, y: CFG.labelYOffset,
        fill: '#c8c8d8', 'font-size': '11', 'font-weight': '600',
        'text-anchor': 'middle',
        style: 'font-family:Inter,sans-serif; pointer-events:none;'
      }, g);
      txt.textContent = truncate(nd.name, 15);

      // NPC badge
      if (nd.isNpc) {
        svgEl('circle', {
          cx: CFG.nodeRadius - 4, cy: -(CFG.nodeRadius - 4), r: 7,
          fill: '#f59e0b', stroke: '#16162a', 'stroke-width': 2
        }, g);
        var npcTxt = svgEl('text', {
          x: CFG.nodeRadius - 4, y: -(CFG.nodeRadius - 8),
          fill: '#16162a', 'font-size': '7', 'font-weight': '900', 'text-anchor': 'middle',
          style: 'pointer-events:none;'
        }, g);
        npcTxt.textContent = 'NPC';
      }
    }
  }

  // ==================== INTERACTION ====================
  function setupInteraction(s) {
    var svg = s.svg;

    // -- HOVER: highlight group --
    svg.addEventListener('mouseover', function(e) {
      var gi = e.target.getAttribute('data-group');
      if (gi !== null) highlightGroup(s, parseInt(gi));
    });
    svg.addEventListener('mouseout', function(e) {
      if (e.target.getAttribute('data-group') !== null) clearHighlight(s);
    });

    // -- MOUSEDOWN --
    svg.addEventListener('mousedown', function(e) {
      // Node drag
      var nodeEl = e.target.closest ? e.target.closest('.net-node') : null;
      if (!nodeEl) {
        // Fallback for browsers without closest on SVG
        var t = e.target;
        while (t && t !== svg) {
          if (t.classList && t.classList.contains('net-node')) { nodeEl = t; break; }
          t = t.parentNode;
        }
      }

      if (nodeEl) {
        e.preventDefault(); e.stopPropagation();
        var idx = parseInt(nodeEl.getAttribute('data-node-idx'));
        s.dragging = {
          idx: idx,
          smx: e.clientX, smy: e.clientY,
          snx: s.nodes[idx].x, sny: s.nodes[idx].y
        };
        s.dragMoved = false;
        svg.style.cursor = 'grabbing';
        return;
      }

      // Pan
      e.preventDefault();
      s.panning = true;
      s.panStart = {x: e.clientX, y: e.clientY, vx: s.viewBox.x, vy: s.viewBox.y};
      svg.style.cursor = 'grabbing';
    });

    document.addEventListener('mousemove', function(e) {
      var rect = svg.getBoundingClientRect();
      var scX = s.viewBox.w / rect.width, scY = s.viewBox.h / rect.height;

      if (s.dragging) {
        var dx = (e.clientX - s.dragging.smx) * scX;
        var dy = (e.clientY - s.dragging.smy) * scY;
        if (Math.abs(dx) > 3 || Math.abs(dy) > 3) s.dragMoved = true;
        s.nodes[s.dragging.idx].x = s.dragging.snx + dx;
        s.nodes[s.dragging.idx].y = s.dragging.sny + dy;
        updatePositions(s);
      }

      if (s.panning && s.panStart) {
        var dx = (e.clientX - s.panStart.x) * scX;
        var dy = (e.clientY - s.panStart.y) * scY;
        s.viewBox.x = s.panStart.vx - dx;
        s.viewBox.y = s.panStart.vy - dy;
        svg.setAttribute('viewBox',
          s.viewBox.x+' '+s.viewBox.y+' '+s.viewBox.w+' '+s.viewBox.h);
      }
    });

    document.addEventListener('mouseup', function() {
      s.dragging = null;
      s.panning = false;
      svg.style.cursor = 'grab';
    });

    // -- ZOOM (wheel) --
    svg.addEventListener('wheel', function(e) {
      e.preventDefault();
      var scale = e.deltaY > 0 ? 1.08 : 0.92;
      var rect = svg.getBoundingClientRect();
      var mx = (e.clientX - rect.left) / rect.width;
      var my = (e.clientY - rect.top) / rect.height;
      var nw = s.viewBox.w * scale, nh = s.viewBox.h * scale;
      s.viewBox.x += (s.viewBox.w - nw) * mx;
      s.viewBox.y += (s.viewBox.h - nh) * my;
      s.viewBox.w = nw; s.viewBox.h = nh;
      svg.setAttribute('viewBox',
        s.viewBox.x+' '+s.viewBox.y+' '+s.viewBox.w+' '+s.viewBox.h);
    }, {passive: false});

    // -- CLICK on node → open ficha --
    svg.addEventListener('click', function(e) {
      if (s.dragMoved) { s.dragMoved = false; return; }
      var nodeEl = e.target.closest ? e.target.closest('.net-node') : null;
      if (!nodeEl) {
        var t = e.target;
        while (t && t !== svg) {
          if (t.classList && t.classList.contains('net-node')) { nodeEl = t; break; }
          t = t.parentNode;
        }
      }
      if (nodeEl) {
        var idx = parseInt(nodeEl.getAttribute('data-node-idx'));
        var nd = s.nodes[idx];
        if (nd.pjId && !nd.isNpc) {
          window.open('personaje.php?pj=' + nd.pjId, '_blank');
        }
      }
    });
  }

  function updatePositions(s) {
    var els = s.gNodes.querySelectorAll('.net-node');
    for (var i = 0; i < els.length; i++) {
      els[i].setAttribute('transform',
        'translate('+s.nodes[i].x.toFixed(1)+','+s.nodes[i].y.toFixed(1)+')');
    }
    renderHulls(s);
    renderLinks(s);
  }

  function highlightGroup(s, gi) {
    var grp = s.groups[gi];
    if (!grp) return;
    var mem = {};
    for (var i = 0; i < grp.members.length; i++) mem[grp.members[i]] = true;

    // Hulls
    var hulls = s.gHulls.querySelectorAll('.net-hull');
    for (var i = 0; i < hulls.length; i++) {
      if (parseInt(hulls[i].getAttribute('data-group')) !== gi) {
        hulls[i].style.opacity = CFG.dimmedOpacity;
      } else {
        hulls[i].style.opacity = '';
        hulls[i].setAttribute('fill-opacity', 0.2);
      }
    }
    // Labels
    var labels = s.gHulls.querySelectorAll('.net-group-label');
    for (var i = 0; i < labels.length; i++)
      labels[i].style.opacity = parseInt(labels[i].getAttribute('data-group')) !== gi ? CFG.dimmedOpacity : '';
    // Links
    var links = s.gLinks.querySelectorAll('.net-link');
    for (var i = 0; i < links.length; i++) {
      if (parseInt(links[i].getAttribute('data-group')) !== gi) {
        links[i].style.opacity = CFG.dimmedOpacity;
      } else {
        links[i].style.opacity = '';
        links[i].setAttribute('stroke-opacity', 0.7);
      }
    }
    // Nodes
    var nodeEls = s.gNodes.querySelectorAll('.net-node');
    for (var i = 0; i < nodeEls.length; i++) {
      if (!mem[s.nodes[i].id]) nodeEls[i].style.opacity = CFG.dimmedOpacity;
    }
  }

  function clearHighlight(s) {
    var hulls = s.gHulls.querySelectorAll('.net-hull');
    for (var i = 0; i < hulls.length; i++) {
      hulls[i].style.opacity = ''; hulls[i].setAttribute('fill-opacity', CFG.hullFillOpacity);
    }
    var labels = s.gHulls.querySelectorAll('.net-group-label');
    for (var i = 0; i < labels.length; i++) labels[i].style.opacity = '';
    var links = s.gLinks.querySelectorAll('.net-link');
    for (var i = 0; i < links.length; i++) {
      links[i].style.opacity = ''; links[i].setAttribute('stroke-opacity', CFG.linkOpacity);
    }
    var nodeEls = s.gNodes.querySelectorAll('.net-node');
    for (var i = 0; i < nodeEls.length; i++) nodeEls[i].style.opacity = '';
  }

  // ==================== INIT ====================
  function init() {
    var data = window.__PJ_NETWORK_DATA;
    if (!data || !data.relations || !data.relations.length) return;
    var container = document.getElementById('pj-network-container');
    if (!container) return;
    var state = buildGraph(container, data);
    window.__PJ_NETWORK_STATE = state;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
