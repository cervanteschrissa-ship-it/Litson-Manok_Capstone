<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Marinduqueño's Litson Manok</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>

    <!-- Navigation Bar -->
    @include('partials.navbar')

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Branch Management Dashboard</h1>
            <p>Select a specific branch below to monitor real-time production, update inventory stock, and track total sales performance.</p>
            <a href="#branches" class="btn-primary">View Branches</a>
        </div>

        <div class="hero-image-container">
            <div class="gradient-fade"></div>
        </div>
    </section>

    <!-- Branches -->
    <section id="branches" class="dashboard-section">
        <h2>Select a Branch</h2>

        <div class="branch-grid">

            <div class="branch-card">
                <img src="{{ asset('images/ccalauan.jpg') }}" alt="Branch 1 - Calauan Plaza">
                <div class="card-content">
                    <h3>Branch 1 - Calauan Plaza</h3>
                    <p>Main hub located at Calauan Plaza, known for high production output.</p>
                    <a href="/branch/1" class="btn-secondary">View Data</a>
                </div>
            </div>

            <div class="branch-card">
                <img src="{{ asset('images/dayap.jpg') }}" alt="Branch 2 - Dayap Palengke">
                <div class="card-content">
                    <h3>Branch 2 - Dayap Palengke</h3>
                    <p>Community-centered branch at Dayap Palengke, steady growth and loyal customers.</p>
                    <a href="/branch/2" class="btn-secondary">View Data</a>
                </div>
            </div>

            <div class="branch-card">
                <img src="{{ asset('images/sanpablo.jpg') }}" alt="Branch 3 - San Pablo City">
                <div class="card-content">
                    <h3>Branch 3 - San Pablo City</h3>
                    <p>Expanding operations in San Pablo City, promising new opportunities.</p>
                    <a href="/branch/3" class="btn-secondary">View Data</a>
                </div>
            </div>

        </div>
    </section>

</body>
</html>