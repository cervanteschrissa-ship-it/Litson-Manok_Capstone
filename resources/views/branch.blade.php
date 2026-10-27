<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Branch {{ $branch_id }} Dashboard</title>
  <link href="{{ asset('vendor/bootstrap.min.css') }}" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  <script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
  <style>
     body { background-color: #f4f7f6; }
     .page-title { color: #125721; font-weight: 900; }
     .custom-card {
         border: none;
         border-top: 4px solid #2ea347;
         box-shadow: 0 4px 15px rgba(0,0,0,0.05);
         margin-bottom: 2rem;
         border-radius: 10px;
     }
     .custom-header {
         background-color: white;
         color: #2ea347;
         font-weight: 700;
         border-bottom: 1px solid #eee;
         padding: 15px 20px;
         border-radius: 10px 10px 0 0 !important;
     }
     .btn-green { background-color: #2ea347; color: white; font-weight: bold; border: none; }
     .btn-green:hover { background-color: #125721; color: white; }
     .btn-red { background-color: #c0392b; color: white; border: none; }
     .btn-red:hover { background-color: #922b21; color: white; }
     .records-table { font-size: 0.9rem; }
     .badge-green { background-color: #2ea347; }
     .badge-red { background-color: #c0392b; }
     .badge-green, .badge-red { font-size: 0.9rem; font-weight: 600; }
     .heading-green { color: #2ea347; }
     .heading-red { color: #c0392b; }
  </style>
</head>
<body>

  <!-- Navigation Bar -->
 @include('partials.navbar', ['margin' => 'mb-4'])

  <div class="container pb-5">

    <!-- Title and Back Button -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Branch {{ $branch_id }} - {{ $branch->branch_name }} Dashboard</h2>
        <a href="/" class="btn btn-green shadow-sm px-4">⬅ Back</a>
    </div>

    @if ($errors->any())
      <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
          <div>{{ $error }}</div>
        @endforeach
      </div>
    @endif

    @if (session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <!-- LEFT COLUMN: PRODUCTION -->
        <div class="col-lg-6">

            <!-- Production Input Form -->
            <div class="card custom-card">
              <div class="card-header custom-header">🍗 Production Input</div>
              <div class="card-body">
                <form method="POST" action="/add-production">
                  @csrf
                  <input type="hidden" name="branch_id" value="{{ $branch_id }}">
                  <div class="mb-3">
                    <label for="chicken_qty" class="form-label text-muted fw-bold small">Grilled Fresh Chicken Quantity (Actual)</label>
                    <input type="number" class="form-control" id="chicken_qty" name="chicken_qty" min="0" required>
                  </div>
                  <div class="mb-3">
                    <label for="planned_quantity" class="form-label text-muted fw-bold small">Planned Quantity (optional)</label>
                    <input type="number" class="form-control" id="planned_quantity" name="planned_quantity" min="0">
                  </div>
                  <button type="submit" class="btn btn-green w-100 py-2">Save Production</button>
                </form>
              </div>
            </div>

            <!-- Production Records Table -->
            <div class="card custom-card">
              <div class="card-header custom-header">📊 Production Records</div>
              <div class="card-body p-0">
                <table class="table table-hover mb-0 records-table">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-3">Date</th>
                      <th>Product</th>
                      <th>Planned</th>
                      <th>Actual</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse ($production_records as $record)
                    <tr>
                      <td class="ps-3">{{ $record->production_date }}</td>
                      <td>{{ $record->product }}</td>
                      <td>{{ $record->planned_quantity }}</td>
                      <td><span class="badge badge-green">{{ $record->chicken_qty }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="ps-3 text-muted">No production records yet.</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Production Trend Chart -->
            <div class="card custom-card">
              <div class="card-header custom-header">📈 Production Trend</div>
              <div class="card-body p-4">
                <canvas id="prodChart" height="220"></canvas>
              </div>
            </div>

        </div>

        <!-- RIGHT COLUMN: INVENTORY -->
        <div class="col-lg-6">

            <!-- Inventory Input Form
                 Owner: type a new item (existing items show as suggestions)
                 Staff: pick from the dropdown only -->
            <div class="card custom-card">
              <div class="card-header custom-header">📦 Inventory Input</div>
              <div class="card-body">
                <form method="POST" action="/add-inventory">
                  @csrf
                  <input type="hidden" name="branch_id" value="{{ $branch_id }}">

                  <div class="mb-3">
                    <label for="item_name" class="form-label text-muted fw-bold small">Item Name</label>

                    @if (auth()->user()->role === 'owner')
                      <select id="item_select" class="form-select" required>
                        <option value="" disabled selected>-- Select item --</option>
                        @foreach ($inventoryItems as $name)
                          <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                        <option value="__new__">+ Add new item...</option>
                      </select>
                      <input type="text" id="item_new" class="form-control mt-2 d-none"
                             placeholder="Type the new item name" maxlength="100">
                      <input type="hidden" name="item_name" id="item_name">
                    @else
                      <select name="item_name" id="item_name" class="form-select" required>
                        <option value="" disabled {{ old('item_name') ? '' : 'selected' }}>-- Select item --</option>
                        @foreach ($inventoryItems as $name)
                          <option value="{{ $name }}" {{ old('item_name') === $name ? 'selected' : '' }}>
                            {{ $name }}
                          </option>
                        @endforeach
                      </select>
                    @endif
                  </div>

                  <div class="mb-3">
                    <label for="stock_qty" class="form-label text-muted fw-bold small">Stock Quantity</label>
                    <input type="number" name="stock_qty" id="stock_qty"
                           class="form-control" min="1" value="{{ old('stock_qty') }}" required>
                  </div>

                  <button type="submit" class="btn btn-green w-100 py-2">Save Inventory</button>
                </form>
              </div>
            </div>

            <!-- Inventory Records Table -->
            <div class="card custom-card">
              <div class="card-header custom-header">📊 Inventory Records</div>
              <div class="card-body p-0">
                <table class="table table-hover mb-0 records-table">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-3">Date</th>
                      <th>Item</th>
                      <th>Stock Qty</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse ($inventory_activity as $row)
                    <tr>
                      <td class="ps-3">{{ $row['date'] }}</td>
                      <td>{{ $row['item'] }}</td>
                      <td>
                        @if ($row['used'])
                          <span class="badge badge-red">-{{ $row['qty'] }}</span>
                        @else
                          <span class="badge badge-green">{{ $row['qty'] }}</span>
                        @endif
                      </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="ps-3 text-muted">No inventory yet.</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Use Stock: pick from items that exist in this branch -->
            <div class="card p-3 mt-4 shadow-sm">
              <h5 class="heading-red">➖ Use Stock</h5>
              <form action="/use-inventory" method="POST" class="row g-3">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch_id }}">
                <div class="col-md-4">
                  <label for="use_item_name" class="visually-hidden">Item name</label>
                  <select id="use_item_name" name="item_name" class="form-select" required>
                    <option value="" disabled {{ old('item_name') ? '' : 'selected' }}>-- Select item --</option>
                    @foreach ($inventory_records->pluck('item_name')->unique() as $name)
                      <option value="{{ $name }}" {{ old('item_name') === $name ? 'selected' : '' }}>
                        {{ $name }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-4">
                  <label for="use_qty" class="visually-hidden">Quantity to use</label>
                  <input type="number" id="use_qty" name="qty_used" class="form-control" placeholder="Quantity to use" min="1" required>
                </div>
                <div class="col-md-4">
                  <button type="submit" class="btn btn-red">Use</button>
                </div>
              </form>
            </div>

            <!-- Inventory Chart -->
            <div class="card custom-card mt-4">
              <div class="card-header custom-header">📉 Inventory Stock</div>
              <div class="card-body p-4">
                <canvas id="invChart" height="220"></canvas>
              </div>
            </div>

        </div>
    </div>
  </div>

  <script src="{{ asset('vendor/bootstrap.bundle.min.js') }}"></script>
  <script>
    // Owner: show a text box when "+ Add new item..." is chosen
    const itemSelect = document.getElementById('item_select');
    if (itemSelect) {
      const itemNew = document.getElementById('item_new');
      const itemHidden = document.getElementById('item_name');

      itemSelect.addEventListener('change', () => {
        const isNew = itemSelect.value === '__new__';
        itemNew.classList.toggle('d-none', !isNew);
        itemNew.required = isNew;
        if (isNew) itemNew.focus();
      });

      itemSelect.form.addEventListener('submit', () => {
        itemHidden.value = itemSelect.value === '__new__'
          ? itemNew.value.trim()
          : itemSelect.value;
      });
    }

    new Chart(document.getElementById('prodChart'), {
      type: 'line',
      data: {
        labels: @json($production_records->pluck('production_date')),
        datasets: [{
          label: 'Chicken production',
          data: @json($production_records->pluck('chicken_qty')),
          borderColor: '#2ea347',
          backgroundColor: 'rgba(46,163,71,0.15)',
          tension: 0.25,
          fill: true
        }]
      }
    });

    new Chart(document.getElementById('invChart'), {
      type: 'bar',
      data: {
        labels: @json($inventory_records->pluck('item_name')),
        datasets: [{
          label: 'Stock quantity',
          data: @json($inventory_records->pluck('stock_qty')),
          backgroundColor: '#2ea347'
        }]
      }
    });
  </script>
</body>
</html>