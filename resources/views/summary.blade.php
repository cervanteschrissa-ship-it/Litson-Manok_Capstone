<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manukan BI Dashboard</title>
  <link href="{{ asset('vendor/bootstrap.min.css') }}" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  <style>
    body { background-color: #f4f7f6; }
    .page-title { color: #125721; font-weight: 900; }
    .custom-card { border: none; border-top: 4px solid #2ea347; box-shadow: 0 4px 15px rgba(0,0,0,.05); border-radius: 10px; margin-bottom: 1.5rem; }
    .custom-header { background: #fff; color: #2ea347; font-weight: 700; border-bottom: 1px solid #eee; padding: 14px 20px; border-radius: 10px 10px 0 0 !important; }
    .kpi { background: #fff; border-radius: 10px; padding: 18px; box-shadow: 0 4px 15px rgba(0,0,0,.05); border-left: 5px solid #2ea347; height: 100%; }
    .kpi .label { color: #777; font-size: .78rem; font-weight: 700; text-transform: uppercase; }
    .kpi .value { color: #125721; font-size: 1.9rem; font-weight: 800; line-height: 1.2; }
    .kpi.danger { border-left-color: #c0392b; } .kpi.danger .value { color: #c0392b; }
    .kpi.warn { border-left-color: #f39c12; }
    .chart-box { position: relative; height: 300px; }
    .btn-green { background: #2ea347; color: #fff; font-weight: bold; border: none; }
    .btn-green:hover { background: #125721; color: #fff; }
    .layer { font-size: .7rem; letter-spacing: .5px; }
    .records-table { font-size: .9rem; }
  </style>
</head>
<body>

@include('partials.navbar', ['margin' => 'mb-4'])

<div class="container pb-5">

  <!-- Title + branch filter -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <h2 class="page-title mb-0">Branch Summary Dashboard</h2>
    <form method="GET" class="d-flex gap-2">
      <select name="branch" aria-label="Filter by branch" class="form-select" onchange="this.form.submit()">
        <option value="all" {{ $selected === 'all' ? 'selected' : '' }}>All branches</option>
        @foreach ($branch_list as $b)
          <option value="{{ $b['id'] }}" {{ (string) $selected === (string) $b['id'] ? 'selected' : '' }}>{{ $b['name'] }}</option>
        @endforeach
      </select>
      <button class="btn btn-green px-3" type="button" onclick="window.print()">Print</button>
    </form>
  </div>

  <!-- KPI cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="kpi">
      <div class="label">Total production</div>
      <div class="value">{{ $kpi['total'] }}</div>
      <small class="text-muted">avg {{ $kpi['avg'] }} / day</small>
    </div></div>
    <div class="col-6 col-lg-3"><div class="kpi {{ (!is_null($kpi['change']) && $kpi['change'] < 0) ? 'danger' : '' }}">
      <div class="label">Last 7 days vs previous</div>
      <div class="value">
        @if (is_null($kpi['change']))
          n/a
        @else
          {{ $kpi['change'] >= 0 ? '▲' : '▼' }} {{ abs($kpi['change']) }}%
        @endif
      </div>
      <small class="text-muted">week-over-week</small>
    </div></div>
    <div class="col-6 col-lg-3"><div class="kpi">
      <div class="label">Forecast (next 7 days)</div>
      <div class="value">{{ $kpi['forecast7'] }}</div>
      <small class="text-muted">moving average</small>
    </div></div>
    <div class="col-6 col-lg-3"><div class="kpi {{ $low_count > 0 ? 'danger' : '' }}">
      <div class="label">Items needing restock</div>
      <div class="value">{{ $low_count }}</div>
      <small class="text-muted">{{ $kpi['anomalies'] }} production anomaly(ies) · revenue ₱{{ number_format($kpi['revenue'], 2) }}</small>
    </div></div>
  </div>

  <!-- Prescriptive recommendations -->
  <div class="card custom-card">
    <div class="card-header custom-header">💡 Recommended Actions <span class="badge bg-dark layer ms-2">PRESCRIPTIVE</span></div>
    <div class="card-body">
      @foreach ($recs as $r)
        <div class="alert alert-{{ $r['level'] }} py-2 mb-2">{{ $r['text'] }}</div>
      @endforeach
    </div>
  </div>

  <div class="row">
    <!-- Production -->
    <div class="col-lg-7">
      <div class="card custom-card">
        <div class="card-header custom-header">📈 Production Trend &amp; Forecast
          <span class="badge bg-secondary layer ms-2">DESCRIPTIVE + PREDICTIVE</span></div>
        <div class="card-body">
          @if (count($chart['labels']))
            <div class="chart-box"><canvas id="prodChart"></canvas></div>
            <small class="text-muted">Red bars = anomalies (more than 2 standard deviations from the average). Missing days are shown as 0.</small>
          @else
            <p class="text-muted mb-0">No production data yet.</p>
          @endif
        </div>
      </div>
    </div>
    <!-- Inventory -->
    <div class="col-lg-5">
      <div class="card custom-card">
        <div class="card-header custom-header">📦 Stock vs Reorder Level
          <span class="badge bg-secondary layer ms-2">DIAGNOSTIC</span></div>
        <div class="card-body">
          @if (count($inv_chart['labels']))
            <div class="chart-box"><canvas id="invChart"></canvas></div>
          @else
            <p class="text-muted mb-0">No inventory data yet.</p>
          @endif
        </div>
      </div>
    </div>
  </div>

  <!-- Inventory health table -->
  <div class="card custom-card">
    <div class="card-header custom-header">🚨 Inventory Health</div>
    <div class="card-body p-0 table-responsive">
      <table class="table table-hover mb-0 records-table">
        <thead class="table-light"><tr>
          <th class="ps-3">Branch</th><th>Item</th><th>Stock</th><th>Reorder at</th>
          <th>Days of cover</th><th>Suggested order</th><th>Status</th>
        </tr></thead>
        <tbody>
          @forelse ($inventory_rows as $x)
            @php
              $badge = $x['status'] === 'Critical' ? 'bg-danger' : ($x['status'] === 'Low' ? 'bg-warning text-dark' : 'bg-success');
            @endphp
            <tr>
              <td class="ps-3">{{ $x['branch'] }}</td>
              <td>{{ $x['item'] }}</td>
              <td>{{ $x['stock'] }}</td>
              <td>{{ $x['reorder'] }}</td>
              <td>{{ $x['cover'] ?? '–' }}</td>
              <td>{{ $x['suggest'] ?: '–' }}</td>
              <td><span class="badge {{ $badge }}">{{ $x['status'] }}</span></td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-3">No inventory records.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @unless ($has_log)
      <div class="card-footer text-muted small">Run <code>migration.sql</code> to enable days-of-cover and suggested orders.</div>
    @endunless
  </div>

  <!-- Branch comparison -->
  <div class="card custom-card">
    <div class="card-header custom-header">🏆 Branch Comparison <span class="badge bg-secondary layer ms-2">DIAGNOSTIC</span></div>
    <div class="card-body p-0 table-responsive">
      <table class="table table-hover mb-0 records-table">
        <thead class="table-light"><tr>
          <th class="ps-3">Rank</th><th>Branch</th><th>Total produced</th><th>Revenue</th><th>Sell-through</th><th>Low-stock items</th>
        </tr></thead>
        <tbody>
          @forelse ($comp as $c)
            <tr>
              <td class="ps-3">#{{ $loop->iteration }}</td>
              <td><a href="/branch/{{ $c['id'] }}">{{ $c['branch'] }}</a></td>
              <td>{{ $c['produced'] }}</td>
              <td>₱{{ number_format($c['revenue'], 2) }}</td>
              <td>{{ !is_null($c['sell_through']) ? $c['sell_through'] . '%' : '–' }}</td>
              <td>{{ $c['low'] }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-3">No data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>

<script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
<script type="application/json" id="prod-data">@json($chart)</script>
<script type="application/json" id="inv-data">@json($inv_chart)</script>
<script>
  const prodData = JSON.parse(document.getElementById('prod-data').textContent);
  const invData = JSON.parse(document.getElementById('inv-data').textContent);

  if (prodData.labels.length) {
    const labels = prodData.labels.concat(prodData.fc_labels);
    const pad = new Array(prodData.labels.length - 1).fill(null);
    const lastAvg = prodData.ma7[prodData.ma7.length - 1];
    new Chart(document.getElementById('prodChart'), {
      data: {
        labels: labels,
        datasets: [
          { type: 'bar', label: 'Production', data: prodData.qty,
            backgroundColor: prodData.anom.map(a => a ? '#c0392b' : '#2ea347') },
          { type: 'line', label: '7-day average', data: prodData.ma7, borderColor: '#125721', pointRadius: 0, tension: 0.3 },
          { type: 'line', label: 'Forecast', data: pad.concat([lastAvg], prodData.fc),
            borderColor: '#f39c12', borderDash: [6, 4], pointRadius: 2 }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, title: { display: true, text: 'Chickens' } } } }
    });
  }

  if (invData.labels.length) {
    new Chart(document.getElementById('invChart'), {
      type: 'bar',
      data: {
        labels: invData.labels,
        datasets: [
          { label: 'Stock', data: invData.stock, backgroundColor: invData.colors },
          { label: 'Reorder level', data: invData.reorder, backgroundColor: '#bbb' }
        ]
      },
      options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        scales: { x: { beginAtZero: true } } }
    });
  }
</script>
</body>
</html>