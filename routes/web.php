<?php
use App\Models\SalesOrderOutstanding;
use App\Services\AxaptaSyncService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Http\Controllers\CustomerSyncController;

Route::post('/sync-axapta', [CustomerSyncController::class, 'syncFromAxapta'])->name('sync.axapta');

// Route untuk membuka halaman tampilan customers
Route::get('/customers', function () {
    return view('customers');
});

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

// Route::get('/tes-tarik-ax', function () {
//     try {
//         $data = DB::connection('sqlsrv_ax')
//             ->table('SOOUTSTANDING59')
//             ->where('tcn_sotype', 0)
//             ->whereRaw("LTRIM(RTRIM(dimension3_)) <> 'SAMPLE'")
//             ->limit(5)
//             ->get();

//         return response()->json([
//             'status' => 'Berhasil Connect & Narik Data!',
//             'total' => count($data),
//             'sample_data' => $data
//         ]);
//     } catch (\Exception $e) {
//         return response()->json([
//             'status' => 'Gagal Connect',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// });

// Route::get('/cek-kolom-ax', function () {
//     try {
//         // Query untuk mengambil daftar kolom dari tabel SOOUTSTANDING59
//         $columns59 = DB::connection('sqlsrv_ax')
//             ->select("SELECT COLUMN_NAME, DATA_TYPE 
//                       FROM INFORMATION_SCHEMA.COLUMNS 
//                       WHERE TABLE_NAME = 'SOOUTSTANDING59'");

//         // Ambil 1 baris sampel data untuk melihat contoh isinya
//         $sampleData = DB::connection('sqlsrv_ax')
//             ->table('SOOUTSTANDING59')
//             ->first();

//         return response()->json([
//             'status' => 'Berhasil mengambil struktur tabel!',
//             'total_kolom' => count($columns59),
//             'daftar_kolom' => $columns59,
//             'sampel_data' => $sampleData
//         ]);
//     } catch (\Exception $e) {
//         return response()->json([
//             'status' => 'Gagal membaca kolom',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// });

Route::get('/tes-sync-so-41', function (AxaptaSyncService $syncService) {
    try {
        $syncService->syncSalesOrderOutstandings();

        $totalData = SalesOrderOutstanding::count();
        $sampleData = SalesOrderOutstanding::latest()->first();

        return response()->json([
            'status' => 'Berhasil Tarik SO Outstanding (BU 59 & 61) 41 Kolom Lengkap!',
            'total_data' => $totalData,
            'sampel_data' => $sampleData
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'Gagal Sync',
            'error' => $e->getMessage()
        ], 500);
    }
});

// ROUTE SEMENTARA: Untuk mengecek daftar seluruh nama kolom di tabel INVENTTABLE Axapta
Route::get('/cek-kolom', function (AxaptaSyncService $syncService) {
    return $syncService->checkInventtableColumns();
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';