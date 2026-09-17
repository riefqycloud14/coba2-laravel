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

        // 1. Sync Cabang Pontianak (BU 61)
        $this->processBranchTransaction([
            'bu'       => '61',
            'username' => 'IT61',
            'password' => 'ITPTK61Erl101',
            'tglAwal'  => $tglAwal,
            'tglAkhir' => $tglAkhir,
            'label'    => 'Pontianak',
        ]);

        // 2. Sync Cabang Samarinda (BU 59)
        $this->processBranchTransaction([
            'bu'       => '59',
            'username' => 'IT59',
            'password' => '59SMD101Erl',
            'tglAwal'  => $tglAwal,
            'tglAkhir' => $tglAkhir,
            'label'    => 'Samarinda',
        ]);

        // 3. Jalankan Pengayaan Detail (Populate Namlan & Sekolah)
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
                'login_timeout'            => 60,
                'connect_timeout'          => 60,
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

                $invoiceNo  = trim($invArray['nofak'] ?? $invArray['NOFAK'] ?? '');
                $accountNum = trim($invArray['kodlan'] ?? $invArray['orderaccount'] ?? '');

                if (!empty($invoiceNo)) {
                    $subtot     = (float) ($invArray['subtot'] ?? 0);
                    $total      = (float) ($invArray['total'] ?? 0);
                    $trDisc     = (float) ($invArray['tr'] ?? 0);
                    $discPercen = (float) ($invArray['discpercent'] ?? $invArray['DISCPERCENT'] ?? 0);

                    $rawType = strtoupper(trim($invArray['type'] ?? 'SALES'));
                    $type = (str_contains($rawType, 'RETUR') || str_contains($rawType, 'RETURN')) ? 'RETUR' : 'SALES';

                    $sumberDana = strtoupper(trim($invArray['sumberdanaid'] ?? $invArray['SUMBERDANAID'] ?? ''));
                    $program    = strtoupper(trim($invArray['dimension3_'] ?? ''));

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
                        'invoice_no' => $invoiceNo,
                        'bu'         => $config['bu'],
                        'tglfak'     => !empty($tglFakRaw) ? Carbon::parse($tglFakRaw)->format('Y-m-d') : $tglAwal,
                        'tglax'      => isset($invArray['erl_invoicedate']) ? Carbon::parse($invArray['erl_invoicedate'])->format('Y-m-d') : null,
                        'type'       => $type,
                        'accountnum' => $accountNum,
                        'salesunit'  => trim($invArray['salesunitid'] ?? ''),
                        'sumberdana' => $sumberDana,
                        'program'    => $program,
                        'swa'        => $swa,
                        'periode'    => 'A2A SAMA',
                        'subtot'     => $subtot,
                        'total'      => $total,
                        'tr'         => $trDisc,
                        'trnum'      => (float) ($invArray['trnum_val'] ?? 0),
                        'totaltr'    => $totalTR,
                        'trbiaya'    => $trBiaya,
                        'totalbiaya' => $totalBiaya,
                        'netto'      => $netto,
                        'nettbiaya'  => $nettBiaya,
                        'updated_at' => now(),
                    ];
                }
            }

            if (!empty($invoiceData)) {
                foreach (array_chunk($invoiceData, 500) as $chunk) {
                    DB::table('invoices')->upsert(
                        $chunk,
                        ['invoice_no', 'bu'],
                        [
                            'tglfak', 'tglax', 'type', 'accountnum', 'salesunit', 'sumberdana', 
                            'program', 'swa', 'periode', 'subtot', 'total', 'tr', 'trnum', 
                            'totaltr', 'trbiaya', 'totalbiaya', 'netto', 'nettbiaya',
                            'updated_at'
                        ]
                    );
                }
            }
        } catch (Exception $e) {
            Log::error("Gagal Sync Cabang {$config['label']}: " . $e->getMessage());
            throw new Exception("Gagal Sync Cabang {$config['label']}: " . $e->getMessage());
        }
    }

    /**
     * TAHAP 2: Penarikan Master Customer (29 Field) dari Axapta ke MySQL
     */
    public function syncCustomers()
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $branches = [
            ['bu' => '61', 'username' => 'IT61', 'password' => 'ITPTK61Erl101', 'label' => 'Pontianak'],
            ['bu' => '59', 'username' => 'IT59', 'password' => '59SMD101Erl', 'label' => 'Samarinda'],
            ['bu' => '81', 'username' => 'IT81', 'password' => 'bJm81@erL',     'label' => 'Banjarmasin'],
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
            }
        }
    }

    /**
     * TAHAP 3: Penarikan Master Barang / Buku dari Axapta ke MySQL
     */
    public function syncItems()
    {
        set_time_limit(0);

        config([
            'database.connections.sqlsrv_axapta' => [
                'driver'                   => 'sqlsrv',
                'host'                     => '10.1.1.64',
                'port'                     => '1433',
                'database'                 => 'DataCollaboration',
                'username'                 => 'IT61',
                'password'                 => 'ITPTK61Erl101',
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
            $items = DB::connection('sqlsrv_axapta')->table('item')->get();

            $itemData = [];
            foreach ($items as $item) {
                $arr = (array) $item;
                $itemId = trim($arr['itemid'] ?? $arr['ITEMID'] ?? '');

                if (!empty($itemId)) {
                    $itemData[] = [
                        'itemid'     => $itemId,
                        'itemname'   => trim($arr['itemname'] ?? $arr['ITEMNAME'] ?? ''),
                        'harga'      => (float) ($arr['amount'] ?? $arr['AMOUNT'] ?? 0),
                        'updated_at' => now(),
                    ];
                }
            }

            if (!empty($itemData)) {
                foreach (array_chunk($itemData, 500) as $chunk) {
                    DB::table('items')->upsert(
                        $chunk,
                        ['itemid'],
                        ['itemname', 'harga', 'updated_at']
                    );
                }
            }
        } catch (Exception $e) {
            Log::error("Gagal Sync Master Item: " . $e->getMessage());
        }
    }

    /**
     * TAHAP 4: Update Detail Invoice (Namlan & Sekolah) dari Tabel Customers
     */
    public function enrichInvoiceDetails()
    {
        $this->syncCustomers();

        DB::statement("
            UPDATE invoices i
            JOIN customers c ON i.accountnum = c.accountnum AND i.bu = c.bu
            SET i.namlan = c.name,
                i.sekolah = c.agr_schoolid
            WHERE i.namlan IS NULL OR i.namlan = ''
        ");
    }
}