<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Summary Dashboard</title>
  <!-- Link to Bootstrap for table layout -->
  <link rel="stylesheet" href="{{ asset('vendor/bootstrap.min.css') }}">
  <!-- Link to your custom green styling -->
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body class="page-bg">

  <!-- Navigation Bar -->
  @include('partials.navbar')

  <div class="container mt-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">🏠 Production Summary</h2>
        <a href="/" class="btn btn-green text-white">⬅ Back to Branches</a>
    </div>

    <!-- Styled Summary Table -->
    <div class="card shadow-sm border-0 summary-card">
      <div class="card-header bg-white py-3 border-bottom-0">
        <h5 class="mb-0 card-title-green">Branch Production Totals</h5>
      </div>
      <div class="card-body pt-0">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Branch</th>
              <th>Total Chickens Produced</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($summary_records as $record)
            <tr>
              <td class="fw-medium">Branch {{ $record->branch_id }} - {{ $record->branch_name }}</td>
              <td><span class="badge badge-green">{{ $record->total_chickens }}</span></td>
            </tr>
            @empty
            <tr>
              <td colspan="2">No branches yet.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  </div>
</body>
</html>