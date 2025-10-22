<?php /* index.php (rediseñado) */ ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Dashboard de Monitoreo Oracle</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Chart.js + time adapter + datalabels -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
  <style>
    body { background:#f8f9fa; }
    .kpi-card { min-height:118px; }
    .kpi-value { font-size:1.6rem; font-weight:700; line-height:1; }
    .kpi-sub { font-size:.85rem; color:#6c757d; }
    .help { cursor:help; color:#6c757d; }
    .card-header .bi { opacity:.8; }
    .chart-wrap { position:relative; height:200px; }
    .spinner-sm { width:1rem; height:1rem; border-width:.15rem;}
    .sticky-controls { position:sticky; top:0; z-index:1000; background:rgba(248,249,250,.95); backdrop-filter:saturate(180%) blur(6px); }
    code { white-space:pre-wrap; }
  </style>
</head>
<body>
<div class="container py-3">

  <!-- Header + Controls -->
  <div class="sticky-controls border-bottom pb-2 mb-3">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <h1 class="h3 mb-0">Monitoreo Básico Oracle</h1>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <div class="text-muted small">Actualiza en:</div>
        <select id="refreshSel" class="form-select form-select-sm" style="width:120px">
          <option value="10000">10 s</option>
          <option value="30000" selected>30 s</option>
          <option value="60000">1 min</option>
          <option value="0">Manual</option>
        </select>
        <button id="btnRefresh" class="btn btn-sm btn-primary">
          <i class="bi bi-arrow-clockwise"></i> Actualizar
        </button>
        <span class="small text-muted ms-2" id="lastUpdated">—</span>
      </div>
    </div>
  </div>

  <!-- KPIs -->
  <div id="kpiRow" class="row g-3">
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card kpi-card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div class="kpi-sub">Host CPU % <i class="bi bi-info-circle help" title="Porcentaje de CPU total del servidor (todas las apps)."></i></div>
            <span id="kpiHostCpuBadge" class="badge bg-secondary">—</span>
          </div>
          <div id="kpiHostCpu" class="kpi-value">—</div>
          <div class="kpi-sub">última muestra</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card kpi-card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div class="kpi-sub">DB CPU % <i class="bi bi-info-circle help" title="Porcentaje de la CPU total consumida por Oracle."></i></div>
            <span id="kpiDbCpuBadge" class="badge bg-secondary">—</span>
          </div>
          <div id="kpiDbCpu" class="kpi-value">—</div>
          <div class="kpi-sub">última muestra</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card kpi-card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div class="kpi-sub">PGA Hit % <i class="bi bi-info-circle help" title="Porcentaje de operaciones en PGA sin volcar a disco."></i></div>
            <span id="kpiPgaHitBadge" class="badge bg-secondary">—</span>
          </div>
          <div id="kpiPgaHit" class="kpi-value">—</div>
          <div class="kpi-sub">eficiencia</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card kpi-card border-0 shadow-sm">
        <div class="card-body">
          <div class="kpi-sub">SGA Total</div>
          <div id="kpiSga" class="kpi-value">—</div>
          <div class="kpi-sub">MB</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card kpi-card border-0 shadow-sm">
        <div class="card-body">
          <div class="kpi-sub">PGA Total</div>
          <div id="kpiPga" class="kpi-value">—</div>
          <div class="kpi-sub">MB</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-8 col-lg-2">
      <div class="card kpi-card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div class="kpi-sub">Último backup <i class="bi bi-info-circle help" title="Último respaldo en el controlfile (RMAN)."></i></div>
            <span id="kpiBkpBadge" class="badge bg-secondary">—</span>
          </div>
          <div id="kpiBkp" class="kpi-value" style="font-size:1.1rem">—</div>
          <div class="kpi-sub" id="kpiBkpSub">—</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Rendimiento -->
  <section class="mt-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header">
        <i class="bi bi-activity me-2"></i>Rendimiento (CPU & Memoria)
      </div>
      <div class="card-body">
        <div id="perfAlert"></div>
        <div class="chart-wrap mb-3"><canvas id="cpuChart"></canvas></div>

        <div class="row g-3">
          <div class="col-lg-6">
            <div class="card border-0">
              <div class="card-header">SGA por componente (MB)</div>
              <div class="card-body">
                <ul id="sgaList" class="list-group list-group-flush"></ul>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="card border-0">
              <div class="card-header">PGA (MB)</div>
              <div class="card-body">
                <ul id="pgaList" class="list-group list-group-flush"></ul>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- Almacenamiento -->
  <section class="mt-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header">
        <i class="bi bi-hdd-stack me-2"></i>Almacenamiento (Tablespaces)
      </div>
      <div class="card-body">
        <div id="tsAlert"></div>
        <div class="chart-wrap"><canvas id="tsChart"></canvas></div>
        <div class="text-muted small mt-2">Umbrales: <span class="badge bg-success">OK &lt; 70%</span> <span class="badge bg-warning text-dark">Alto 70–85%</span> <span class="badge bg-danger">Crítico &gt; 85%</span></div>
      </div>
    </div>
  </section>

  <!-- Top SQL -->
  <section class="mt-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-lightning-charge me-2"></i>Top 5 Consultas</span>
        <ul class="nav nav-pills" id="sqlTabs">
          <li class="nav-item"><button class="nav-link active" data-type="cpu">Por CPU</button></li>
          <li class="nav-item"><button class="nav-link" data-type="elapsed">Por Tiempo</button></li>
        </ul>
      </div>
      <div class="card-body">
        <div id="topSqlAlert"></div>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr><th>SQL_ID</th><th>Owner</th><th>Execs</th><th>CPU (s)</th><th>Elapsed (s)</th><th>SQL (300c)</th></tr>
            </thead>
            <tbody id="topSqlBody">
              <tr><td colspan="6" class="text-center text-muted py-4"><div class="spinner-border spinner-sm me-2" role="status"></div>Cargando...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <!-- Conexiones e Inválidos -->
  <section class="mt-4">
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header"><i class="bi bi-people me-2"></i>Conexiones / Sesiones</div>
          <div class="card-body">
            <div id="connAlert"></div>
            <div id="connSummary" class="mb-3"></div>
            <div class="table-responsive">
              <table class="table table-sm">
                <thead><tr><th>SID</th><th>Usuario</th><th>Status</th><th>Máquina</th><th>Programa</th><th>Login</th></tr></thead>
                <tbody id="connDetail"><tr><td colspan="6" class="text-center text-muted py-3">Cargando...</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="bi bi-bug me-2"></i>Objetos Inválidos</span>
            <div class="d-flex gap-2">
              <button class="btn btn-sm btn-outline-success" onclick="gatherStats()"><i class="bi bi-gear"></i> Recalcular estadísticas</button>
              <button class="btn btn-sm btn-outline-warning" onclick="recompileInvalids()"><i class="bi bi-wrench"></i> Recompilar inválidos</button>
            </div>
          </div>
          <div class="card-body">
            <div id="invAlert"></div>
            <div class="table-responsive">
              <table class="table table-sm">
                <thead><tr><th>Owner</th><th>Tipo</th><th>Objeto</th><th>Status</th></tr></thead>
                <tbody id="invalidsBody"><tr><td colspan="4" class="text-center text-muted py-3">Cargando...</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <footer class="text-muted small my-4">
    <div>EIF402 – Administración de Bases de Datos · Universidad Nacional</div>
  </footer>
</div>

<script>
let cpuChart, tsChart, refreshTimer;

// ====== CONFIGURABLE ======
const WINDOW_MIN = 15; // ventana móvil para el gráfico de rendimiento (minutos)
// ==========================

// Utils
const $ = (s) => document.querySelector(s);
const fmtInt = (n) => isFinite(n) ? Math.round(n).toLocaleString() : '—';
const fmt1  = (n) => isFinite(n) ? Number(n).toFixed(1) : '—';
const nowStr = () => new Date().toLocaleString();

/* === Estado acumulado (persistente entre refresh) === */
const perfState = { host: [], db: [], pga: [] }; // arrays de {x:Date, y:Number}

function parseOracleTs(s) {
  if (!s || typeof s !== 'string') return new Date();
  // Formatos típicos: "YYYY-MM-DD HH24:MI:SS" -> usar 'T' para Date
  return new Date(s.replace(' ', 'T'));
}
function pushPoint(series, ts, val) {
  const x = ts ? parseOracleTs(ts) : new Date();
  const y = Number(val);
  if (!isFinite(y)) return;
  const last = series[series.length - 1];
  if (!last || x > last.x) series.push({ x, y });
}
function trimWindow(series) {
  const cutoff = Date.now() - WINDOW_MIN * 60_000;
  while (series.length && series[0].x.getTime() < cutoff) series.shift();
}
function trimAll() { [perfState.host, perfState.db, perfState.pga].forEach(trimWindow); }

function badgeLevel(val, thr={warn:70, danger:85}) {
  if(!isFinite(val)) return 'bg-secondary';
  if(val >= thr.danger) return 'bg-danger';
  if(val >= thr.warn) return 'bg-warning text-dark';
  return 'bg-success';
}

async function fetchJSON(url) {
  const r = await fetch(url);
  if(!r.ok) throw new Error(`${url} -> ${r.status}`);
  return r.json();
}
function setAlert(targetId, msg, type='danger'){ $(targetId).innerHTML = `<div class="alert alert-${type}">${msg}</div>`; }
function clearAlert(targetId){ $(targetId).innerHTML=''; }

// ====== MÉTRICAS (CPU/Memoria) ======
async function loadMetrics(){
  try {
    clearAlert('#perfAlert');
    const m = await fetchJSON('api/metrics.php');

    // Alimenta histórico (si no viene sys, también agregamos punto "ahora" con los últimos valores conocidos)
    const rows = Array.isArray(m.sys) ? m.sys : [];
    if (rows.length) {
      rows.slice().reverse().forEach(r => {
        const ts = r.SAMPLE_TIME || new Date().toISOString().slice(0,19).replace('T',' ');
        pushPoint(perfState.host, ts, r.HOST_CPU_PCT);
        pushPoint(perfState.db,   ts, r.DB_CPU_RATIO);
        pushPoint(perfState.pga,  ts, r.PGA_HIT_PCT);
      });
    } else {
      // Si tu endpoint aún no trae histórico, toma KPIs “ahora” para que la línea avance cada refresh
      const ts = new Date().toISOString().slice(0,19).replace('T',' ');
      // Si no tienes DB_CPU_RATIO / PGA_HIT_PCT, caerán como NaN y no se pintarán (está bien).
      pushPoint(perfState.host, ts, m.host_cpu_pct ?? m.HOST_CPU_PCT ?? null);
      pushPoint(perfState.db,   ts, m.db_cpu_ratio ?? m.DB_CPU_RATIO ?? null);
      pushPoint(perfState.pga,  ts, m.pga_hit_pct ?? m.PGA_HIT_PCT ?? null);
    }

    // recorta a la ventana de N minutos
    trimAll();

    // KPIs: últimos puntos
    const lastHost = perfState.host.at(-1)?.y;
    const lastDb   = perfState.db.at(-1)?.y;
    const lastPga  = perfState.pga.at(-1)?.y;

    $('#kpiHostCpu').textContent = isFinite(lastHost) ? `${fmt1(lastHost)}%` : '—';
    $('#kpiDbCpu').textContent   = isFinite(lastDb)   ? `${fmt1(lastDb)}%`   : '—';
    $('#kpiPgaHit').textContent  = isFinite(lastPga)  ? `${fmt1(lastPga)}%`  : '—';

    $('#kpiHostCpuBadge').className = `badge ${badgeLevel(lastHost)}`;
    $('#kpiDbCpuBadge').className   = `badge ${badgeLevel(lastDb)}`;
    $('#kpiPgaHitBadge').className  =
      `badge ${ lastPga>=95 ? 'bg-success' : lastPga>=90 ? 'bg-warning text-dark' : 'bg-danger' }`;

    // SGA y PGA (listas + totales)
    const sgaUl = $('#sgaList'); sgaUl.innerHTML='';
    let sgaTotal = 0;
    (m.sga||[]).forEach(r=>{
      const mb = Number(r.CURRENT_MB||r.current_mb||0);
      sgaTotal += mb;
      const li = document.createElement('li');
      li.className='list-group-item d-flex justify-content-between';
      li.innerHTML = `<span>${(r.COMPONENT||r.component||'').replaceAll('_',' ')}</span><strong>${fmt1(mb)} MB</strong>`;
      sgaUl.appendChild(li);
    });
    $('#kpiSga').textContent = fmtInt(sgaTotal);

    const pgaUl = $('#pgaList'); pgaUl.innerHTML='';
    let pgaTotal = 0;
    (m.pga||[]).forEach(r=>{
      const mb = Number(r.VALUE_MB||r.value_mb||0);
      pgaTotal += mb;
      const li = document.createElement('li');
      li.className='list-group-item d-flex justify-content-between';
      li.innerHTML = `<span>${r.NAME||r.name||''}</span><strong>${fmt1(mb)} MB</strong>`;
      pgaUl.appendChild(li);
    });
    $('#kpiPga').textContent = fmtInt(pgaTotal);

    // Gráfico (temporal en minutos)
    if (!cpuChart) {
      cpuChart = new Chart($('#cpuChart'),{
        type:'line',
        data:{ datasets:[
          {label:'Host CPU %', data: perfState.host},
          {label:'DB CPU %',   data: perfState.db},
          {label:'PGA Hit %',  data: perfState.pga}
        ]},
        options:{
          parsing:false,
          responsive:true,
          maintainAspectRatio:false,
          interaction:{mode:'index', intersect:false},
          plugins:{ legend:{ position:'bottom' } },
          scales:{
            x:{
              type:'time',
              time:{ unit:'minute', stepSize:1, displayFormats:{ minute:'HH:mm' } },
              adapters: {} // usa date-fns
            },
            y:{ suggestedMin:0, suggestedMax:100, ticks:{ callback:v=> v+'%' } }
          },
          animation:false
        }
      });
    } else {
      cpuChart.data.datasets[0].data = perfState.host;
      cpuChart.data.datasets[1].data = perfState.db;
      cpuChart.data.datasets[2].data = perfState.pga;
      cpuChart.update('none');
    }
  } catch (e) {
    setAlert('#perfAlert', 'No se pudieron cargar métricas de rendimiento. ' + e.message);
  }
}

// ====== TABLESPACES (con % usado en etiqueta) ======
async function loadTablespaces(){
  try {
    clearAlert('#tsAlert');
    const ts = await fetchJSON('api/tablespaces.php');

    const labels = ts.map(r=>r.TABLESPACE_NAME);
    const used   = ts.map(r=>Number(r.USED_MB||r.used_mb||0));
    const total  = ts.map(r=>Number(r.TOTAL_MB||r.total_mb||0));
    const free   = total.map((t,i)=> Math.max(t - used[i], 0));
    const pct    = total.map((t,i)=> t>0 ? Math.round((used[i]/t)*100) : 0);

    if(tsChart) tsChart.destroy();
    tsChart = new Chart($('#tsChart'),{
      type:'bar',
      data:{ labels,
        datasets:[
          {label:'Usado (MB)', data:used, stack:'x'},
          {label:'Libre (MB)', data:free, stack:'x'}
        ]
      },
      options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{
          legend:{ position:'bottom' },
          datalabels:{
            display:(ctx)=> ctx.datasetIndex===0, // sólo en la barra "Usado"
            formatter:(val, ctx)=> {
              const i = ctx.dataIndex;
              return (pct[i] || 0) + '%';
            },
            anchor:'end',
            align:'end',
            clamp:true,
            offset:-2,
            font:{ weight:'700' },
            color:(ctx)=>{
              const i = ctx.dataIndex, p = pct[i]||0;
              return p>=85 ? '#dc3545' : p>=70 ? '#ffc107' : '#198754'; // rojo/amarillo/verde
            }
          }
        },
        scales:{ x:{ stacked:true }, y:{ stacked:true } }
      },
      plugins:[ChartDataLabels]
    });
  } catch (e) {
    setAlert('#tsAlert', 'No se pudo cargar almacenamiento. ' + e.message);
  }
}

// ====== BACKUPS ======
async function loadBackup(){
  try{
    const b = await fetchJSON('api/backups.php');
    let end=null, status='—', bytes=null;
    if(Array.isArray(b) && b.length){
      const r=b[0];
      end = r.LAST_BACKUP_END || r.COMPLETION_TIME;
      status = r.LAST_STATUS || r.BACKUP_TYPE || '—';
      bytes = r.BYTES || r.bytes;
    }
    const badge = $('#kpiBkpBadge'), v=$('#kpiBkp'), sub=$('#kpiBkpSub');
    if(end){
      const d = new Date(end);
      const ageHrs = (Date.now()-d.getTime())/3600000;
      badge.className = 'badge ' + (ageHrs>168 ? 'bg-danger' : ageHrs>48 ? 'bg-warning text-dark' : 'bg-success');
      v.textContent = d.toLocaleString();
      sub.textContent = `Tipo/Estado: ${status}${bytes? ' · Tamaño aprox: ' + Intl.NumberFormat().format(bytes/1024/1024) + ' MB' : ''}`;
    } else {
      badge.className='badge bg-secondary';
      v.textContent = 'Sin registros';
      sub.innerHTML = '';
    }
  }catch(e){
    $('#kpiBkpBadge').className='badge bg-secondary';
    $('#kpiBkp').textContent='—';
    $('#kpiBkpSub').textContent='No se pudo consultar backups.';
  }
}

// ====== TOP SQL ======
async function loadTopSQL(type='cpu'){
  try{
    clearAlert('#topSqlAlert');
    const rows = await fetchJSON('api/top_sql.php?type='+type);
    const tb = $('#topSqlBody'); tb.innerHTML='';
    (rows||[]).slice(0,5).forEach(r=>{
      const tr=document.createElement('tr');
      tr.innerHTML = `
        <td>${r.SQL_ID||''}</td>
        <td>${r.OWNER||''}</td>
        <td>${r.EXECUTIONS||0}</td>
        <td>${r.CPU_SEC||''}</td>
        <td>${r.ELAPSED_SEC||''}</td>
        <td><code>${(r.SQL_TEXT||'').substring(0,300).replaceAll('<','&lt;')}</code></td>`;
      tb.appendChild(tr);
    });
    if(!rows || !rows.length) $('#topSqlBody').innerHTML = `<tr><td colspan="6" class="text-center text-muted py-3">Sin datos</td></tr>`;
  }catch(e){
    setAlert('#topSqlAlert','No se pudo cargar Top SQL. '+e.message);
  }
}

// ====== CONEXIONES ======
async function loadConnections(){
  try{
    clearAlert('#connAlert');
    const c = await fetchJSON('api/connections.php');
    const sumDiv = $('#connSummary');
    sumDiv.innerHTML = (c.summary||[]).map(s=>`<span></span>`).join('') || '<span class="text-muted">Sin datos</span>';
    const tb = $('#connDetail'); tb.innerHTML='';
    (c.detail||[]).forEach(r=>{
      const tr=document.createElement('tr');
      tr.innerHTML = `
        <td>${r.SID}</td>
        <td>${r.USERNAME||''}</td>
        <td>${r.STATUS}</td>
        <td>${r.MACHINE||''}</td>
        <td>${r.PROGRAM||''}</td>
        <td>${r.LOGON_TIME}</td>`;
      tb.appendChild(tr);
    });
    if(!(c.detail||[]).length) tb.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-3">Sin sesiones</td></tr>`;
  }catch(e){
    setAlert('#connAlert','No se pudieron cargar sesiones. '+e.message);
  }
}

// ====== INVÁLIDOS ======
async function loadInvalids(){
  try{
    clearAlert('#invAlert');
    const inv = await fetchJSON('api/invalids.php');
    const tb = $('#invalidsBody'); tb.innerHTML='';
    (inv||[]).forEach(r=>{
      const tr=document.createElement('tr');
      tr.innerHTML = `<td>${r.OWNER}</td><td>${r.OBJECT_TYPE}</td><td>${r.OBJECT_NAME}</td><td>${r.STATUS}</td>`;
      tb.appendChild(tr);
    });
    if(!(inv||[]).length) tb.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">Sin inválidos</td></tr>`;
  }catch(e){
    setAlert('#invAlert','No se pudo listar inválidos. '+e.message);
  }
}

// ====== Acciones de mantenimiento ======
async function gatherStats(){
  const owner = prompt('Esquema para recalcular estadísticas:', 'MONITOR');
  if(!owner) return;
  try{
    const r = await fetch('actions/gather_stats.php?owner='+encodeURIComponent(owner));
    const j = await r.json(); alert(j.msg||'OK');
  }catch(e){ alert('Error: '+e.message); }
}
async function recompileInvalids(){
  const owner = prompt('Esquema para recompilar inválidos:', 'MONITOR');
  if(!owner) return;
  try{
    const r = await fetch('actions/recompile_invalids.php?owner='+encodeURIComponent(owner));
    const j = await r.json(); alert(j.msg||'OK');
  }catch(e){ alert('Error: '+e.message); }
}

// ====== Orquestación ======
async function refreshAll(){
  await Promise.all([
    loadMetrics(),
    loadTablespaces(),
    loadBackup(),
    loadTopSQL(activeSqlTab),
    loadConnections(),
    loadInvalids()
  ]);
  $('#lastUpdated').textContent = 'Actualizado: ' + nowStr();
}

// Tabs Top SQL
let activeSqlTab = 'cpu';
document.querySelectorAll('#sqlTabs .nav-link').forEach(btn=>{
  btn.addEventListener('click', async (e)=>{
    document.querySelectorAll('#sqlTabs .nav-link').forEach(b=>b.classList.remove('active'));
    e.target.classList.add('active');
    activeSqlTab = e.target.dataset.type;
    await loadTopSQL(activeSqlTab);
  });
});

// Refresh controls
$('#btnRefresh').addEventListener('click', refreshAll);
$('#refreshSel').addEventListener('change', (e)=>{
  if(refreshTimer) clearInterval(refreshTimer);
  const ms = Number(e.target.value||0);
  if(ms>0) refreshTimer = setInterval(refreshAll, ms);
});

// Primera carga
refreshAll();
const initMs = Number($('#refreshSel').value);
if(initMs>0) refreshTimer = setInterval(refreshAll, initMs);
</script>
</body>
</html>
