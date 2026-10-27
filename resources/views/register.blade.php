<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - Marinduqueño's Litson Manok</title>
  <link href="{{ asset('vendor/bootstrap.min.css') }}" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  <style>
    body { background-color: #f4f7f6; }
    .register-card { max-width: 450px; margin: 40px auto; border: none; border-top: 4px solid #2ea347; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border-radius: 10px; }
    .btn-green { background-color: #2ea347; color: white; font-weight: bold; border: none; }
    .btn-green:hover { background-color: #125721; color: white; }
  </style>
</head>
<body>
  <header class="navbar">
    <div class="logo">
      <img src="{{ asset('images/logo.png') }}" alt="Marinduqueño's Litson Manok" class="header-logo">
    </div>
  </header>

  <div class="container">
    <div class="card register-card">
      <div class="card-body p-4">
        <h4 class="mb-3" style="color:#125721; font-weight:800;">Create Staff Account</h4>

        @if ($errors->any())
          <div class="alert alert-danger py-2">
            <ul class="mb-0 small">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <form method="POST" action="/register">
          @csrf
          <div class="mb-3">
            <label for="name" class="form-label fw-bold small text-muted">Full Name</label>
            <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required autofocus>
          </div>
          <div class="mb-3">
            <label for="username" class="form-label fw-bold small text-muted">Username</label>
            <input type="text" class="form-control" id="username" name="username" value="{{ old('username') }}" required>
          </div>
          <div class="mb-3">
            <label for="password" class="form-label fw-bold small text-muted">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
          </div>
          <div class="mb-3">
            <label for="password_confirmation" class="form-label fw-bold small text-muted">Confirm Password</label>
            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
          </div>
          <button type="submit" class="btn btn-green w-100 py-2">Register</button>
        </form>

        <p class="text-center small mt-3 mb-0">
          Already have an account? <a href="/login">Log in here</a>
        </p>
      </div>
    </div>
  </div>
</body>
</html>