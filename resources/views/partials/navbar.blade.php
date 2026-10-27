<header class="navbar {{ $margin ?? '' }}">
  <div class="logo">
    <img src="{{ asset('images/logo.png') }}" alt="Marinduqueño's Litson Manok" class="header-logo">
  </div>
  <nav class="nav-links">
    <a href="/" class="{{ request()->is('/') ? 'active' : '' }}">DASHBOARD</a>
    <a href="/#branches" class="{{ request()->is('branch/*') ? 'active' : '' }}">BRANCHES</a>
    @if (auth()->user()?->isOwner())
      <a href="/summary" class="{{ request()->is('summary') ? 'active' : '' }}">SUMMARY</a>
    @endif
    <span style="color:#fff; font-weight:600; margin-left:20px;">{{ auth()->user()->name }} ({{ auth()->user()->role }})</span>
    <form method="POST" action="/logout" style="display:inline;">
      @csrf
      <button type="submit" class="btn btn-sm btn-light ms-2">Logout</button>
    </form>
  </nav>
</header>