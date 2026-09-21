<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class AxaptaSyncService
{
    /**
     * TAHAP 1: Sync Transaksi Invoice per Cabang
     */
    public function syncTransactionAndCustomer($tglAwal, $tglAkhir)
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $branches = [
            ['bu' => '61', 'username' => 'IT61', 'password' => 'ITPTK61Erl101', 'label' => 'Pontianak'],
            ['bu' => '59', 'username' => 'IT59', 'password' => '59SMD101Erl', 'label' => 'Samarinda'],
            ['bu' => '81', 'username' => 'IT81', 'password' => 'bJm81@erL',    'label' => 'Banjarmasin'],
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
     * TAHAP 2: Penarikan Master Customer
     */
    public function syncCustomers()
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $branches = [
            ['bu' => '61', 'username' => 'IT61', 'password' => 'ITPTK61Erl101', 'label' => 'Pontianak'],
            ['bu' => '59', 'username' => 'IT59', 'password' => '59SMD101Erl', 'label' => 'Samarinda'],
            ['bu' => '81', 'username' => 'IT81', 'password' => 'bJm81@erL',    'label' => 'Banjarmasin'],
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
                    'login_timeout'            => 60,
                    'connect_timeout'          => 60,
                ]
            ]);

            DB::purge('sqlsrv_axapta');

            try {
                $customers = DB::connection('sqlsrv_axapta')
                    ->table('customer')
                    ->where('dimension', $config['bu'])
                    ->get();

                $customerData = [];
                foreach ($customers as $cust) {
                    $arr = (array) $cust;
                    
                    $getVal = function ($key) use ($arr) {
                        foreach ($arr as $k => $v) {
                            if (strtolower($k) === strtolower($key)) {
                                return is_string($v) ? trim($v) : $v;
                            }
                        }
                        return null;
                    };

                    $accountNum = $getVal('accountnum');

                    if (!empty($accountNum)) {
                        $customerData[] = [
                            'accountnum'           => $accountNum,
                            'bu'                   => $config['bu'],
                            'name'                 => $getVal('name'),
                            'address'              => $getVal('address'),
                            'inventsiteid'         => $getVal('inventsiteid'),
                            'agr_schoolid'         => $getVal('agr_schoolid'),
                            'agr_consignmentid'    => $getVal('agr_consignmentid'),
                            'agr_schooltypeid'     => $getVal('agr_schooltypeid'),
                            'county'               => $getVal('county'),
                            'city'                 => $getVal('city'),
                            'state'                => $getVal('state'),
                            'creditmax'            => (float) ($getVal('creditmax') ?? 0),
                            'companychainid'       => $getVal('companychainid'),
                            'zipcode'              => $getVal('zipcode'),
                            'custclassificationid' => $getVal('custclassificationid'),
                            'agr_gradeid'          => $getVal('agr_gradeid'),
                            'dimension'            => $getVal('dimension'),
                            'segmentid'            => $getVal('segmentid'),
                            'erl_be_id'            => $getVal('erl_be_id'),
                            'custgroup'            => $getVal('custgroup'),
                            'subgroupid'           => $getVal('subgroupid'),
                            'subsegmentid'         => $getVal('subsegmentid'),
                            'modifieddatetime'     => $getVal('modifieddatetime'),
                            'createddatetime'      => $getVal('createddatetime'),
                            'swsalesunitid'        => $getVal('swsalesunitid'),
                            'invoiceaccount'       => $getVal('invoiceaccount'),
                            'phone'                => $getVal('phone'),
                            'cellularphone'        => $getVal('cellularphone'),
                            'nikum'                => $getVal('nikum'),
                            'npsn'                 => $getVal('npsn'),
                            'updated_at'           => now(),
                        ];
                    }
                }

                if (!empty($customerData)) {
                    foreach (array_chunk($customerData, 500) as $chunk) {
                        DB::table('customers')->upsert(
                            $chunk,
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
                }
                Log::info("Cabang {$config['label']}: Berhasil sync " . count($customerData) . " master customer.");
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
     * TAHAP 4: Penarikan Stok, Transit & PO Outstanding per Cabang
     */
    public function syncStocks(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $activeItemIds = DB::table('invoices')
            ->whereNotNull('kodbuk')
            ->where('kodbuk', '<>', '')
            ->distinct()
            ->pluck('kodbuk')
            ->toArray();

        if (empty($activeItemIds)) {
            Log::warning("Sync Stok dibatalkan: Tidak ada item/kodbuk di master invoice.");
            return;
        }

        $branches = [
            '59' => ['site' => '59', 'username' => 'IT59', 'password' => '59SMD101Erl',   'qo_table' => 'QUARANTINEORDER59', 'po_table' => 'POINTERNALOUTSTANDING59'],
            '61' => ['site' => '61', 'username' => 'IT61', 'password' => 'ITPTK61Erl101', 'qo_table' => 'QUARANTINEORDER61', 'po_table' => 'POINTERNALOUTSTANDING61'],
            '81' => ['site' => '81', 'username' => 'IT81', 'password' => 'bJm81@erL',      'qo_table' => 'QUARANTINEORDER81', 'po_table' => 'POINTERNALOUTSTANDING81'],
        ];

        $tahunIni = date('Y');

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
                    $stokFisik = DB::connection('sqlsrv_ax_live')
                        ->table('ERL_STOCKPOSITIONDB')
                        ->select(
                            DB::raw("INVENTLOCATIONID as wh"),
                            DB::raw("ITEMID as itemid"),
                            DB::raw("SUM(SUMOFAVAILPHYSICAL) as total_stok")
                        )
                        ->where('INVENTSITEID', $config['site'])
                        ->whereIn('ITEMID', $activeItemIds)
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
                } catch (Exception $e) {
                    Log::error("Gagal Tarik Stok Fisik BU {$bu}: " . $e->getMessage());
                }

                // 2. Stok Transit
                try {
                    $stokTransit = DB::connection('sqlsrv_ax_live')
                        ->table($config['qo_table'])
                        ->select(
                            DB::raw("INVENTLOCATIONID as wh"),
                            DB::raw("ITEMID as itemid"),
                            DB::raw("SUM(QTY) as total_transit")
                        )
                        ->whereIn('ITEMID', $activeItemIds)
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
                } catch (Exception $e) {
                    Log::error("Gagal Tarik Transit BU {$bu}: " . $e->getMessage());
                }

                // 3. PO Outstanding
                try {
                    $poOut = DB::connection('sqlsrv_ax_live')
                        ->table($config['po_table'])
                        ->select(
                            DB::raw("INVENTLOCATIONID as wh"),
                            DB::raw("ITEMID as itemid"),
                            DB::raw("SUM(REMAINPURCHPHYSICAL) as total_po")
                        )
                        ->whereRaw("YEAR(createddatetime1) = ?", [$tahunIni])
                        ->whereIn('ITEMID', $activeItemIds)
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
     * TAHAP 5: Penarikan SO Outstanding Sesuai Kolom Asli Tabel FoxPro
     */
/**
     * TAHAP 5: Penarikan SO Outstanding (Hybrid & Fallback Safe)
     */
    public function syncSalesOrders(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $activeItemIds = DB::table('invoices')
            ->whereNotNull('kodbuk')
            ->where('kodbuk', '<>', '')
            ->distinct()
            ->pluck('kodbuk')
            ->toArray();

        if (empty($activeItemIds)) {
            Log::warning("Sync SO dibatalkan: Tidak ada item/kodbuk di master invoice.");
            return;
        }

        $branches = [
            '81' => [
                'type'     => 'staging',
                'table'    => 'SOOUTSTANDINGDB81',
                'username' => 'IT81',
                'password' => 'bJm81@erL',
                'date_col' => 'CREATEDDATETIME',
            ],
            '59' => [
                'type'     => 'direct',
                'site'     => '59',
                'username' => 'IT59',
                'password' => '59SMD101Erl',
            ],
            '61' => [
                'type'     => 'direct',
                'site'     => '61',
                'username' => 'IT61',
                'password' => 'ITPTK61Erl101',
            ],
        ];

        $thn1 = (int) date('Y');
        $thn2 = $thn1 - 1;

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
                if ($config['type'] === 'staging') {
                    $rawSo = DB::connection('sqlsrv_ax_live')
                        ->table($config['table'])
                        ->whereRaw("(YEAR({$config['date_col']}) = ? OR YEAR({$config['date_col']}) = ?)", [$thn1, $thn2])
                        ->whereRaw("tcn_sotype = 0")
                        ->whereRaw("LTRIM(RTRIM(dimension3_)) <> 'SAMPLE'")
                        ->whereIn('ITEMID', $activeItemIds)
                        ->get();

                    foreach ($rawSo as $inv) {
                        $arr = (array) $inv;
                        $getVal = fn($k) => collect($arr)->first(fn($v, $key) => strtolower($key) === strtolower($k));

                        $salesId = $getVal('salesid');
                        $itemId  = $getVal('itemid');
                        $wh      = $getVal('inventlocationid') ?? $getVal('wh') ?? $bu;

                        if ($wh === '66' || empty($salesId) || empty($itemId)) continue;

                        $qty = (float) ($getVal('qty') ?? 0);
                        $createdDate = $getVal($config['date_col']);

                        DB::table('sales_orders')->updateOrInsert(
                            ['bu' => $bu, 'sales_id' => trim($salesId), 'itemid' => trim($itemId)],
                            [
                                'accountnum'       => trim($getVal('accountnum') ?? ''),
                                'inventlocationid' => trim($wh),
                                'qty_order'        => $qty,
                                'qty_outstanding'  => $qty,
                                'created_date'     => !empty($createdDate) ? Carbon::parse($createdDate)->format('Y-m-d') : null,
                                'updated_at'       => now(),
                            ]
                        );
                    }
                } else {
                    $soData = DB::connection('sqlsrv_ax_live')
                        ->table('SALESLINE')
                        ->join('SALESTABLE', 'SALESLINE.SALESID', '=', 'SALESTABLE.SALESID')
                        ->select(
                            DB::raw("SALESLINE.SALESID as sales_id"),
                            DB::raw("SALESTABLE.CUSTACCOUNT as accountnum"),
                            DB::raw("SALESLINE.INVENTLOCATIONID as wh"),
                            DB::raw("SALESLINE.ITEMID as itemid"),
                            DB::raw("SUM(SALESLINE.QTYORDERED) as total_order"),
                            DB::raw("SUM(SALESLINE.REMAINSALESPHYSICAL) as total_outstanding"),
                            DB::raw("MIN(SALESTABLE.CREATEDDATETIME) as created_date")
                        )
                        ->where('SALESTABLE.INVENTSITEID', $config['site'])
                        ->where('SALESLINE.REMAINSALESPHYSICAL', '>', 0)
                        ->whereIn('SALESLINE.ITEMID', $activeItemIds)
                        ->groupBy('SALESLINE.SALESID', 'SALESTABLE.CUSTACCOUNT', 'SALESLINE.INVENTLOCATIONID', 'SALESLINE.ITEMID')
                        ->get();

                    foreach ($soData as $row) {
                        if (trim($row->wh) === '66') continue;

                        DB::table('sales_orders')->updateOrInsert(
                            ['bu' => $bu, 'sales_id' => trim($row->sales_id), 'itemid' => trim($row->itemid)],
                            [
                                'accountnum'       => trim($row->accountnum),
                                'inventlocationid' => trim($row->wh),
                                'qty_order'        => (float) $row->total_order,
                                'qty_outstanding'  => (float) $row->total_outstanding,
                                'created_date'     => !empty($row->created_date) ? Carbon::parse($row->created_date)->format('Y-m-d') : null,
                                'updated_at'       => now(),
                            ]
                        );
                    }
                }

                Log::info("Sync SO Outstanding Cabang BU {$bu}: Berhasil.");
            } catch (Exception $e) {
                Log::error("Gagal Sync SO Cabang BU {$bu}: " . $e->getMessage());
            } finally {
                DB::disconnect('sqlsrv_ax_live');
            }
        }
    }

    /**
     * TAHAP GABUNGAN: Penarikan Stok Realtime & SO Outstanding Sekaligus
     */
    public function syncStocksAndSalesOrders(): void
    {
        $this->syncStocks();
        $this->syncSalesOrders();
    }
}