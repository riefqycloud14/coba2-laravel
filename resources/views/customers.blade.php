<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pelanggan Cabang - AXAPTA</title>
    <!-- CSS Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Master Pelanggan Cabang</h2>
        
        <!-- Tombol Tarik Data dari AXAPTA -->
        <form action="{{ route('sync.axapta') }}" method="POST" onsubmit="return confirm('Tarik data pelanggan terbaru dari Server AXAPTA?')">
            @csrf
            <button type="submit" class="btn btn-primary fw-bold">
                🔄 Tarik Data Cabang (AXAPTA)
            </button>
        </form>
    </div>

    <!-- Alert Status -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Card Tabel Data -->
    <div class="card shadow-sm">
        <div class="card-body">
            <p class="text-muted">Data terhubung langsung dengan server SQL Server AXAPTA (10.1.1.64).</p>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>No</th>
                            <th>Account Num</th>
                            <th>Nama Customer</th>
                            <th>Alamat</th>
                            <th>Kota</th>
                            <th>No. Telepon</th>
                            <th>Dimension</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                Klik tombol <strong>"Tarik Data Cabang (AXAPTA)"</strong> di atas untuk sinkronisasi data terbaru.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>