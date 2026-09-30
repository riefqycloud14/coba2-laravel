<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class AxaptaSyncService
{
    /**
     * TAHAP 1: Sync Transaksi Invoice per Cabang (Hanya Pontianak & Samarinda)
     */
    public function syncTransactionAndCustomer($tglAwal, $tglAkhir)
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        // Banjarmasin (81) dihapus dari daftar penarikan
        $branches = [
            ['bu' => '61', 'username' => 'IT61', 'password' => 'ITPTK61Erl101', 'label' => 'Pontianak'],
            ['bu' => '59', 'username' => 'IT59', 'password' => '59SMD101Erl', 'label' => 'Samarinda'],
        ];

        foreach ($branches as $branch) {
            $this->processBranchTransaction([
                'bu'       => $branch['bu'],
                'username' => $branch['username'],
                'password' => $branch['password'],
                'tglAwal'  => $tglAwal,
                'tglAkhir' => $tglAkhir,
                'label'    => $branch['label'],
            ]);
        }

        $this->enrichInvoiceDetails();
    }

    private function processBranchTransaction(array $config)
    {
        $tglAwal  = Carbon::parse($config['tglAwal'])->format('Y-m-d');
        $tglAkhir = Carbon::parse($config['tglAkhir'])->format('Y-m-d');

        config([
            'database.connections.sqlsrv_axapta' => [
                'driver'                   => 'sqlsrv',
                'host'                     => '10.1.1.64',
                'port'                     => '1433',
                'database'                 => 'DataCollaboration',
                'username'                 => $config['username'],
                'password'                 => $config['password'],
                'charset'                  => 'utf8',
                'prefix'                   => '',
                'encrypt'                  => env('DB_ENCRYPT', 'no'),
                'trust_server_certificate' => true,
                'login_timeout'            => 120,
                'connect_timeout'          => 120,
            ]
        ]);

        DB::purge('sqlsrv_axapta');

        try {
            $invoices = DB::connection('sqlsrv_axapta')
                ->table('invoice')
                ->leftJoin('commissioncalc', 'invoice.tr', '=', 'commissioncalc.salesreprelation')
                ->select(
                    'invoice.*',
                    'commissioncalc.commissionbase as trnum_val'
                )
                ->whereDate('invoice.tglfak', '>=', $tglAwal)
                ->whereDate('invoice.tglfak', '<=', $tglAkhir)
                ->where('invoice.bu', $config['bu'])
                ->get();

            Log::info("Cabang {$config['label']}: Ditemukan " . count($invoices) . " baris transaksi.");

            $invoiceData = [];

            foreach ($invoices as $inv) {
                $invArray = (array) $inv;

                $invoiceNo   = trim($invArray['nofak'] ?? $invArray['NOFAK'] ?? '');
                $accountNum  = trim($invArray['kodlan'] ?? $invArray['KODLAN'] ?? $invArray['orderaccount'] ?? '');
                $kodBuk      = trim($invArray['kodbuk'] ?? $invArray['KODBUK'] ?? '');
                $idReturn    = trim($invArray['agr_invoiceidreturn'] ?? $invArray['AGR_INVOICEIDRETURN'] ?? '');

                if (!empty($invoiceNo)) {
                    $subtot     = (float) ($invArray['subtot'] ?? 0);
                    $total      = (float) ($invArray['total'] ?? 0);
                    $trDisc     = (float) ($invArray['tr'] ?? 0);
                    $discPercen = (float) ($invArray['discpercent'] ?? $invArray['DISCPERCENT'] ?? 0);

                    $rawType = strtoupper(trim($invArray['type'] ?? 'SALES'));
                    $type = (str_contains($rawType, 'RETUR') || str_contains($rawType, 'RETURN')) ? 'RETUR' : 'SALES';

                    $sumberDana = strtoupper(trim($invArray['sumberdanaid'] ?? $invArray['SUMBERDANAID'] ?? ''));
                    $program    = strtoupper(trim($invArray['dimension3_'] ?? $invArray['DIMENSION3_'] ?? ''));

                    $swa = in_array($sumberDana, ['APBD', 'BOP', 'BOS', 'PROYEK']) ? 'NSW' : 'SW';

                    $trBiaya = 0;
                    if (($discPercen + $trDisc) > 42.5) {
                        $trBiaya = ($discPercen + $trDisc) - 42.5;
                    }

                    $totalTR    = $subtot * ($trDisc / 100);
                    $totalBiaya = ($subtot * $trBiaya) / 100;

                    if ($type === 'RETUR') {
                        $netto     = -(abs($total) - abs($totalTR));
                        $nettBiaya = $netto - $totalBiaya;
                    } else {
                        $netto     = $total - $totalTR;
                        $nettBiaya = $netto - $totalBiaya;
                    }

                    $tglFakRaw = trim($invArray['tglfak'] ?? '');

                    $invoiceData[] = [
                        'invoice_no'            => $invoiceNo,
                        'bu'                    => $config['bu'],
                        'thlap'                 => $invArray['thlap'] ?? null,
                        'kodcab'                => trim($invArray['kodcab'] ?? ''),
                        'kodbuk'                => $kodBuk,
                        'cvaccount'             => trim($invArray['cvaccount'] ?? ''),
                        'agr_invoiceidreturn'   => $idReturn,
                        'agr_invoicedatereturn' => !empty($invArray['agr_invoicedatereturn']) ? Carbon::parse($invArray['agr_invoicedatereturn'])->format('Y-m-d') : null,
                        'tglfak'                => !empty($tglFakRaw) ? Carbon::parse($tglFakRaw)->format('Y-m-d') : $tglAwal,
                        'blfak'                 => $invArray['blfak'] ?? null,
                        'hrfak'                 => $invArray['hrfak'] ?? null,
                        'tglax'                 => !empty($invArray['erl_invoicedate']) ? Carbon::parse($invArray['erl_invoicedate'])->format('Y-m-d') : null,
                        'type'                  => $type,
                        'accountnum'            => $accountNum,
                        'pricegroupid'          => trim($invArray['PriceGroupId'] ?? $invArray['PRICEGROUPID'] ?? ''),
                        'soid'                  => trim($invArray['soid'] ?? ''),
                        'salesunit'             => trim($invArray['salesunitid'] ?? ''),
                        'kodsal'                => trim($invArray['kodsal'] ?? ''),
                        'nosp'                  => trim($invArray['nosp'] ?? ''),
                        'sumberdana'            => $sumberDana,
                        'program'               => $program,
                        'alasan'                => trim($invArray['alasan'] ?? ''),
                        'customerref'           => trim($invArray['customerref'] ?? ''),
                        'payment'               => trim($invArray['payment'] ?? ''),
                        'kwantum'               => (float) ($invArray['kwantum'] ?? 0),
                        'harga'                 => (float) ($invArray['harga'] ?? 0),
                        'subtot'                => $subtot,
                        'total'                 => $total,
                        'discpercent'           => $discPercen,
                        'discamount'            => (float) ($invArray['discamount'] ?? 0),
                        'discrp'                => (float) ($invArray['discrp'] ?? 0),
                        'tr'                    => $trDisc,
                        'trnum'                 => (float) ($invArray['trnum_val'] ?? 0),
                        'totaltr'               => $totalTR,
                        'trbiaya'               => $trBiaya,
                        'totalbiaya'            => $totalBiaya,
                        'netto'                 => $netto,
                        'nettbiaya'             => $nettBiaya,
                        'orderaccount'          => trim($invArray['orderaccount'] ?? ''),
                        'swa'                   => $swa,
                        'periode'               => 'A2A SAMA',
                        'updated_at'            => now(),
                    ];
                }
            }

            if (!empty($invoiceData)) {
                foreach (array_chunk($invoiceData, 500) as $chunk) {
                    DB::table('invoices')->upsert(
                        $chunk,
                        ['invoice_no', 'bu', 'kodbuk'], 
                        [
                            'thlap', 'kodcab', 'cvaccount', 'agr_invoiceidreturn', 'agr_invoicedatereturn', 
                            'tglfak', 'blfak', 'hrfak', 'tglax', 'type', 'accountnum', 'pricegroupid', 
                            'soid', 'salesunit', 'kodsal', 'nosp', 'sumberdana', 'program', 
                            'alasan', 'customerref', 'payment', 'kwantum', 'harga', 'subtot', 
                            'total', 'discpercent', 'discamount', 'discrp', 'tr', 'trnum', 
                            'totaltr', 'trbiaya', 'totalbiaya', 'netto', 'nettbiaya', 
                            'orderaccount', 'swa', 'periode', 'updated_at'
                        ]
                    );
                }
            }
        } catch (Exception $e) {
            Log::error("Gagal Sync Cabang {$config['label']}: " . $e->getMessage());
            throw new Exception("Gagal Sync Cabang {$config['label']}: " . $e->getMessage());
        } finally {
            DB::disconnect('sqlsrv_axapta');
        }
    }

    /**
     * TAHAP 2: Penarikan Master Customer (Hanya BU 59 & 61)
     */
    public function syncCustomers()
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $branches = [
            ['bu' => '61', 'username' => 'IT61', 'password' => 'ITPTK61Erl101', 'label' => 'Pontianak'],
            ['bu' => '59', 'username' => 'IT59', 'password' => '59SMD101Erl', 'label' => 'Samarinda'],
        ];

        foreach ($branches as $config) {
            config([
                'database.connections.sqlsrv_axapta' => [
                    'driver'                   => 'sqlsrv',
                    'host'                     => '10.1.1.64',
                    'port'                     => '1433',
                    'database'                 => 'DataCollaboration',
                    'username'                 => $config['username'],
                    'password'                 => $config['password'],
                    'charset'                  => 'utf8',
                    'prefix'                   => '',
                    'encrypt'                  => env('DB_ENCRYPT', 'no'),
                    'trust_server_certificate' => true,
                    'login_timeout'            => 180,
                    'connect_timeout'          => 180,
                ]
            ]);

            DB::purge('sqlsrv_axapta');

            try {
                DB::connection('sqlsrv_axapta')
                    ->table('customer')
                    ->where('dimension', $config['bu'])
                    ->orderBy('accountnum')
                    ->chunk(1000, function ($customers) use ($config) {
                        $customerData = [];
                        foreach ($customers as $cust) {
                            $arr = (array) $cust;
                            $getVal = fn($k) => collect($arr)->first(fn($v, $key) => strtolower($key) === strtolower($k));

                            $accountNum = $getVal('accountnum');
                            if (!empty($accountNum)) {
                                $customerData[] = [
                                    'accountnum'           => trim($accountNum),
                                    'bu'                   => $config['bu'],
                                    'name'                 => trim($getVal('name') ?? ''),
                                    'address'              => trim($getVal('address') ?? ''),
                                    'inventsiteid'         => trim($getVal('inventsiteid') ?? ''),
                                    'agr_schoolid'         => trim($getVal('agr_schoolid') ?? ''),
                                    'agr_consignmentid'    => trim($getVal('agr_consignmentid') ?? ''),
                                    'agr_schooltypeid'     => trim($getVal('agr_schooltypeid') ?? ''),
                                    'county'               => trim($getVal('county') ?? ''),
                                    'city'                 => trim($getVal('city') ?? ''),
                                    'state'                => trim($getVal('state') ?? ''),
                                    'creditmax'            => (float) ($getVal('creditmax') ?? 0),
                                    'companychainid'       => trim($getVal('companychainid') ?? ''),
                                    'zipcode'              => trim($getVal('zipcode') ?? ''),
                                    'custclassificationid' => trim($getVal('custclassificationid') ?? ''),
                                    'agr_gradeid'          => trim($getVal('agr_gradeid') ?? ''),
                                    'dimension'            => trim($getVal('dimension') ?? ''),
                                    'segmentid'            => trim($getVal('segmentid') ?? ''),
                                    'erl_be_id'            => trim($getVal('erl_be_id') ?? ''),
                                    'custgroup'            => trim($getVal('custgroup') ?? ''),
                                    'subgroupid'           => trim($getVal('subgroupid') ?? ''),
                                    'subsegmentid'         => trim($getVal('subsegmentid') ?? ''),
                                    'modifieddatetime'     => $getVal('modifieddatetime'),
                                    'createddatetime'      => $getVal('createddatetime'),
                                    'swsalesunitid'        => trim($getVal('swsalesunitid') ?? ''),
                                    'invoiceaccount'       => trim($getVal('invoiceaccount') ?? ''),
                                    'phone'                => trim($getVal('phone') ?? ''),
                                    'cellularphone'        => trim($getVal('cellularphone') ?? ''),
                                    'nikum'                => trim($getVal('nikum') ?? ''),
                                    'npsn'                 => trim($getVal('npsn') ?? ''),
                                    'updated_at'           => now(),
                                ];
                            }
                        }

                        if (!empty($customerData)) {
                            DB::table('customers')->upsert(
                                $customerData,
                                ['accountnum', 'bu'],
                                [
                                    'name', 'address', 'inventsiteid', 'agr_schoolid', 'agr_consignmentid',
                                    'agr_schooltypeid', 'county', 'city', 'state', 'creditmax',
                                    'companychainid', 'zipcode', 'custclassificationid', 'agr_gradeid',
                                    'dimension', 'segmentid', 'erl_be_id', 'custgroup', 'subgroupid',
                                    'subsegmentid', 'modifieddatetime', 'createddatetime', 'swsalesunitid',
                                    'invoiceaccount', 'phone', 'cellularphone', 'nikum', 'npsn', 'updated_at'
                                ]
                            );
                        }
                    });

                Log::info("Cabang {$config['label']}: Berhasil sync master customer.");
            } catch (Exception $e) {
                Log::error("Gagal Sync Customer Cabang {$config['label']}: " . $e->getMessage());
            } finally {
                DB::disconnect('sqlsrv_axapta');
            }
        }
    }

    /**
     * TAHAP 3: Update Detail Invoice (Namlan & Sekolah) dari Tabel Customers
     */
    public function enrichInvoiceDetails()
    {
        DB::statement("
            UPDATE invoices i
            JOIN customers c ON i.accountnum = c.accountnum AND i.bu = c.dimension
            SET i.namlan = c.name,
                i.sekolah = c.agr_schoolid
            WHERE i.namlan IS NULL OR i.namlan = ''
        ");
    }

    /**
     * TAHAP TAMBAHAN: Sync Master Item / Buku Lengkap dari INVENTTABLE Axapta
     * Penyesuaian Kolom: AGR_BRANDID & AGR_ITEMGRADE
     */
    public function syncItems(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        config([
            'database.connections.sqlsrv_ax_live' => [
                'driver'                   => 'sqlsrv',
                'host'                     => '172.16.8.13',
                'port'                     => '1433',
                'database'                 => 'Ax_2009_Live',
                'username'                 => 'WebMyAX',
                'password'                 => '753Tokina',
                'charset'                  => 'utf8',
                'prefix'                   => '',
                'encrypt'                  => env('DB_ENCRYPT', 'no'),
                'trust_server_certificate' => true,
                'login_timeout'            => 120,
                'connect_timeout'          => 120,
            ]
        ]);

        DB::purge('sqlsrv_ax_live');

        try {
            DB::connection('sqlsrv_ax_live')
                ->table('INVENTTABLE')
                ->select([
                    'ITEMID as itemid',
                    'ITEMNAME as itemname',
                    'AGR_PENGARANG as agr_pengarang',
                    'AGR_ITEMCURRICULUM as agr_itemcurriculum',
                    'AGR_BRANDID as agr_brandname',     // Menggunakan AGR_BRANDID dari INVENTTABLE
                    'AGR_ITEMGRADE as agr_gradename',   // Menggunakan AGR_ITEMGRADE dari INVENTTABLE
                ])
                ->orderBy('ITEMID')
                ->chunk(1000, function ($items) {
                    $insertData = [];
                    foreach ($items as $item) {
                        $insertData[] = [
                            'itemid'             => trim($item->itemid),
                            'itemname'           => trim($item->itemname ?? ''),
                            'agr_pengarang'      => trim($item->agr_pengarang ?? ''),
                            'agr_itemcurriculum' => trim($item->agr_itemcurriculum ?? ''),
                            'agr_brandname'      => trim($item->agr_brandname ?? ''),
                            'agr_gradename'      => trim($item->agr_gradename ?? ''),
                            'updated_at'         => now(),
                        ];
                    }

                    if (!empty($insertData)) {
                        DB::table('items')->upsert(
                            $insertData,
                            ['itemid'],
                            ['itemname', 'agr_pengarang', 'agr_itemcurriculum', 'agr_brandname', 'agr_gradename', 'updated_at']
                        );
                    }
                });

            Log::info("Sync Master Item dari INVENTTABLE Axapta berhasil.");
        } catch (Exception $e) {
            Log::error("Gagal Sync Master Item: " . $e->getMessage());
        } finally {
            DB::disconnect('sqlsrv_ax_live');
        }
    }

    /**
     * TAHAP 4: Penarikan Stok, Transit & PO Outstanding per Cabang (Hanya BU 59 & 61)
     */
    public function syncStocks(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        // Pastikan Master Item disinkronkan terlebih dahulu
        $this->syncItems();

        // Ambil kodbuk dari transaksi invoice DAN master items lokal
        $activeItemIds = DB::table('invoices')
            ->whereNotNull('kodbuk')
            ->where('kodbuk', '<>', '')
            ->pluck('kodbuk')
            ->merge(DB::table('items')->pluck('itemid'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($activeItemIds)) {
            Log::warning("Sync Stok dibatalkan: Tidak ada item/kodbuk di master invoice atau master items.");
            return;
        }

        $branches = [
            '59' => ['site' => '59', 'username' => 'IT59', 'password' => '59SMD101Erl',   'qo_table' => 'QUARANTINEORDER59', 'po_table' => 'POINTERNALOUTSTANDING59'],
            '61' => ['site' => '61', 'username' => 'IT61', 'password' => 'ITPTK61Erl101', 'qo_table' => 'QUARANTINEORDER61', 'po_table' => 'POINTERNALOUTSTANDING61'],
        ];

        $tahunIni = date('Y');

        // Bagi array item menjadi pecahan maksimal 1.000 item per kelompok
        $itemChunks = array_chunk($activeItemIds, 1000);

        foreach ($branches as $bu => $config) {
            config([
                'database.connections.sqlsrv_ax_live' => [
                    'driver'                   => 'sqlsrv',
                    'host'                     => '172.16.8.13',
                    'port'                     => '1433',
                    'database'                 => 'Ax_2009_Live',
                    'username'                 => $config['username'],
                    'password'                 => $config['password'],
                    'charset'                  => 'utf8',
                    'prefix'                   => '',
                    'encrypt'                  => env('DB_ENCRYPT', 'no'),
                    'trust_server_certificate' => true,
                    'login_timeout'            => 60,
                    'connect_timeout'          => 60,
                ]
            ]);

            DB::purge('sqlsrv_ax_live');

            try {
                // 1. Stok Fisik
                try {
                    foreach ($itemChunks as $chunk) {
                        $stokFisik = DB::connection('sqlsrv_ax_live')
                            ->table('ERL_STOCKPOSITIONDB')
                            ->select(
                                DB::raw("INVENTLOCATIONID as wh"),
                                DB::raw("ITEMID as itemid"),
                                DB::raw("SUM(SUMOFAVAILPHYSICAL) as total_stok")
                            )
                            ->where('INVENTSITEID', $config['site'])
                            ->whereIn('ITEMID', $chunk)
                            ->groupBy('INVENTLOCATIONID', 'ITEMID')
                            ->get();

                        foreach ($stokFisik as $row) {
                            if (trim($row->wh) === '66') continue;

                            DB::table('stocks')->updateOrInsert(
                                [
                                    'bu'               => $bu,
                                    'inventlocationid' => trim($row->wh),
                                    'itemid'           => trim($row->itemid),
                                ],
                                [
                                    'stok_physical'    => (float) $row->total_stok,
                                    'updated_at'       => now(),
                                ]
                            );
                        }
                    }
                } catch (Exception $e) {
                    Log::error("Gagal Tarik Stok Fisik BU {$bu}: " . $e->getMessage());
                }

                // 2. Stok Transit
                try {
                    foreach ($itemChunks as $chunk) {
                        $stokTransit = DB::connection('sqlsrv_ax_live')
                            ->table($config['qo_table'])
                            ->select(
                                DB::raw("INVENTLOCATIONID as wh"),
                                DB::raw("ITEMID as itemid"),
                                DB::raw("SUM(QTY) as total_transit")
                            )
                            ->whereIn('ITEMID', $chunk)
                            ->groupBy('INVENTLOCATIONID', 'ITEMID')
                            ->get();

                        foreach ($stokTransit as $row) {
                            if (trim($row->wh) === '66') continue;

                            DB::table('stocks')->updateOrInsert(
                                [
                                    'bu'               => $bu,
                                    'inventlocationid' => trim($row->wh),
                                    'itemid'           => trim($row->itemid),
                                ],
                                [
                                    'stok_transit'     => (float) $row->total_transit,
                                    'updated_at'       => now(),
                                ]
                            );
                        }
                    }
                } catch (Exception $e) {
                    Log::error("Gagal Tarik Transit BU {$bu}: " . $e->getMessage());
                }

                // 3. PO Outstanding
                try {
                    foreach ($itemChunks as $chunk) {
                        $poOut = DB::connection('sqlsrv_ax_live')
                            ->table($config['po_table'])
                            ->select(
                                DB::raw("INVENTLOCATIONID as wh"),
                                DB::raw("ITEMID as itemid"),
                                DB::raw("SUM(REMAINPURCHPHYSICAL) as total_po")
                            )
                            ->whereRaw("YEAR(createddatetime1) = ?", [$tahunIni])
                            ->whereIn('ITEMID', $chunk)
                            ->groupBy('INVENTLOCATIONID', 'ITEMID')
                            ->get();

                        foreach ($poOut as $row) {
                            if (trim($row->wh) === '66') continue;

                            DB::table('stocks')->updateOrInsert(
                                [
                                    'bu'               => $bu,
                                    'inventlocationid' => trim($row->wh),
                                    'itemid'           => trim($row->itemid),
                                ],
                                [
                                    'po_outstanding'   => (float) $row->total_po,
                                    'updated_at'       => now(),
                                ]
                            );
                        }
                    }
                } catch (Exception $e) {
                    Log::error("Gagal Tarik PO Outstanding BU {$bu}: " . $e->getMessage());
                }

                Log::info("Sync Stok Cabang BU {$bu}: Berhasil.");
            } finally {
                DB::disconnect('sqlsrv_ax_live');
            }
        }
    }

    /**
     * TAHAP 5: Penarikan SO Outstanding 41 Kolom Lengkap
     */
    public function syncSalesOrderOutstandings(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        \App\Models\SalesOrderOutstanding::truncate();

        $branches = [
            '59' => ['table' => 'SOOUTSTANDING59'],
            '61' => ['table' => 'SOOUTSTANDING61'],
        ];

        config([
            'database.connections.sqlsrv_ax_live' => [
                'driver'                   => 'sqlsrv',
                'host'                     => '172.16.8.13',
                'port'                     => '1433',
                'database'                 => 'Ax_2009_Live',
                'username'                 => 'WebMyAX',
                'password'                 => '753Tokina',
                'charset'                  => 'utf8',
                'prefix'                   => '',
                'encrypt'                  => env('DB_ENCRYPT', 'no'),
                'trust_server_certificate' => true,
                'login_timeout'            => 60,
                'connect_timeout'          => 60,
            ]
        ]);

        DB::purge('sqlsrv_ax_live');

        foreach ($branches as $bu => $config) {
            try {
                $rawSo = DB::connection('sqlsrv_ax_live')
                    ->table($config['table'])
                    ->where(function ($q) {
                        $q->whereNull('tcn_sotype')
                          ->orWhere('tcn_sotype', 0)
                          ->orWhere('tcn_sotype', '0');
                    })
                    ->where(function ($q) {
                        $q->whereNull('dimension3_')
                          ->orWhereRaw("LTRIM(RTRIM(dimension3_)) <> 'SAMPLE'");
                    })
                    ->get();

                $insertData = [];

                foreach ($rawSo as $row) {
                    $arr = (array) $row;
                    $getVal = fn($k) => collect($arr)->first(fn($v, $key) => strtolower($key) === strtolower($k));

                    $qty         = (float) ($getVal('qtyordered') ?? $getVal('qty') ?? 0);
                    $soEks       = (int) abs($qty);
                    $price       = (float) ($getVal('salesprice') ?? 0);
                    $linePercent = (float) ($getVal('linepercent') ?? 0);
                    $lineDisc    = (float) ($getVal('linedisc') ?? 0);

                    $gross    = $soEks * $price;
                    $soAmount = $gross - ($gross * ($linePercent / 100));
                    $soNett   = $soAmount - $lineDisc;

                    $dim3 = strtoupper(trim($getVal('dimension3_') ?? ''));
                    $jenisSo = 'REGULER';
                    if (str_contains($dim3, 'SIPLAH')) {
                        $jenisSo = 'SIPLAH';
                    } elseif (str_contains($dim3, 'BOS')) {
                        $jenisSo = 'BOS';
                    } elseif (str_contains($dim3, 'PROYEK')) {
                        $jenisSo = 'PROYEK';
                    }

                    $insertData[] = [
                        'bu'                    => (int) $bu,
                        'so_eks'                => $soEks,
                        'so_amount'             => $soAmount,
                        'so_nett'               => $soNett,
                        'swa'                   => str_contains($dim3, 'SWA') ? 'SWA' : 'NSW',
                        'jenis_so'              => $jenisSo,
                        'sales_id'              => trim($getVal('salesid') ?? ''),
                        'sales_responsible'     => trim($getVal('salesresponsible') ?? ''),
                        'sales_status'          => $getVal('salesstatus'),
                        'sales_unit_id'         => trim($getVal('salesunitid') ?? ''),
                        'agr_sales_resp_name'   => trim($getVal('agr_salesrespname') ?? ''),
                        'created_date_time1'    => $getVal('createddatetime1'),
                        'dimension'             => trim($getVal('dimension') ?? ''),
                        'dimension2_'           => trim($getVal('dimension2_') ?? ''),
                        'dimension3_'           => $dim3,
                        'tcn_sotype'            => $getVal('tcn_sotype'),
                        'approval_status'       => $getVal('approvalstatus'),
                        'customer_ref'          => trim($getVal('customerref') ?? ''),
                        'purch_order_form_num'  => trim($getVal('purchorderformnum') ?? ''),
                        'dataareaid'            => trim($getVal('dataareaid') ?? ''),
                        'recid'                 => $getVal('recid'),
                        'dataareaid_2'          => trim($getVal('dataareaid#2') ?? ''),
                        'item_id'               => trim($getVal('itemid') ?? ''),
                        'qty_ordered'           => $qty,
                        'sales_price'           => $price,
                        'line_disc'             => $lineDisc,
                        'sales_group'           => trim($getVal('salesgroup') ?? ''),
                        'line_amount'           => (float) ($getVal('lineamount') ?? 0),
                        'invent_dim_id'         => trim($getVal('inventdimid') ?? ''),
                        'line_percent'          => $linePercent,
                        'dataareaid_3'          => trim($getVal('dataareaid#3') ?? ''),
                        'account_num'           => trim($getVal('accountnum') ?? ''),
                        'agr_school_id'         => trim($getVal('agr_schoolid') ?? ''),
                        'dataareaid_4'          => trim($getVal('dataareaid#4') ?? ''),
                        'item_name'             => trim($getVal('itemname') ?? ''),
                        'agr_pengarang'         => trim($getVal('agr_pengarang') ?? ''),
                        'agr_brand_name'        => trim($getVal('agr_brandname') ?? ''),
                        'agr_grade_name'        => trim($getVal('agr_gradename') ?? ''),
                        'agr_item_curriculum'   => trim($getVal('agr_itemcurriculum') ?? ''),
                        'agr_item_segment'      => trim($getVal('agr_itemsegment') ?? ''),
                        'agr_publish_date'      => $getVal('agr_publishdate'),
                        'agr_publish_year'      => $getVal('agr_publishyear'),
                        'dataareaid_5'          => trim($getVal('dataareaid#5') ?? ''),
                        'qty'                   => (float) ($getVal('qty') ?? 0),
                        'status_issue'          => $getVal('statusissue'),
                        'dataareaid_6'          => trim($getVal('dataareaid#6') ?? ''),
                        'erl_invent_location_id'=> trim($getVal('erl_inventlocationid') ?? ''),
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ];
                }

                if (!empty($insertData)) {
                    foreach (array_chunk($insertData, 500) as $chunk) {
                        DB::table('sales_order_outstandings')->insert($chunk);
                    }
                }

                Log::info("Sync Full SO Outstanding (SOOUTSTANDING{$bu}): Berhasil ditarik " . count($insertData) . " baris.");
            } catch (Exception $e) {
                Log::error("Gagal Sync Full SO Outstanding BU {$bu}: " . $e->getMessage());
            }
        }

        DB::disconnect('sqlsrv_ax_live');
    }

    /**
     * TAHAP GABUNGAN: Penarikan Stok Realtime & SO Outstanding Sekaligus
     */
    public function syncStocksAndSalesOrders(): void
    {
        $this->syncStocks();
        $this->syncSalesOrderOutstandings();
    }

    // /**
    //  * METHOD SEMENTARA: Mengecek Daftar Seluruh Kolom di Tabel INVENTTABLE Axapta
    //  */
    // public function checkInventtableColumns()
    // {
    //     config([
    //         'database.connections.sqlsrv_ax_live' => [
    //             'driver'                   => 'sqlsrv',
    //             'host'                     => '172.16.8.13',
    //             'port'                     => '1433',
    //             'database'                 => 'Ax_2009_Live',
    //             'username'                 => 'WebMyAX',
    //             'password'                 => '753Tokina',
    //             'charset'                  => 'utf8',
    //             'prefix'                   => '',
    //             'encrypt'                  => env('DB_ENCRYPT', 'no'),
    //             'trust_server_certificate' => true,
    //         ]
    //     ]);

    //     $columns = DB::connection('sqlsrv_ax_live')
    //         ->select("
    //             SELECT COLUMN_NAME 
    //             FROM INFORMATION_SCHEMA.COLUMNS 
    //             WHERE TABLE_NAME = 'INVENTTABLE' 
    //             ORDER BY COLUMN_NAME
    //         ");

    //     $columnNames = array_map(fn($col) => $col->COLUMN_NAME, $columns);

    //     dd($columnNames);
    // }
}