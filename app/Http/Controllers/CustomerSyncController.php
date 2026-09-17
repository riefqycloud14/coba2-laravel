<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class CustomerSyncController extends Controller
{
    public function syncFromAxapta()
    {
        try {
            // 1. TARIK DATA SAMARINDA (dimension = 59)
            $smdCustomers = DB::connection('axapta_smd')
                ->table('customer')
                ->where('dimension', '59')
                ->get();

            DB::table('customers_samarinda')->truncate();
            $batchSmd = [];
            foreach ($smdCustomers as $cust) {
                $batchSmd[] = [
                    'accountnum' => $cust->ACCOUNTNUM ?? null,
                    'name'       => $cust->NAME ?? null,
                    'address'    => $cust->ADDRESS ?? null,
                    'inventsite' => $cust->INVENTSITE ?? null,
                    'agr_school' => $cust->AGR_SCHOOL ?? null,
                    'city'       => $cust->CITY ?? null,
                    'dimension'  => $cust->DIMENSION ?? '59',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($batchSmd) >= 500) {
                    DB::table('customers_samarinda')->insert($batchSmd);
                    $batchSmd = [];
                }
            }
            if (!empty($batchSmd)) {
                DB::table('customers_samarinda')->insert($batchSmd);
            }

            // 2. TARIK DATA PONTIANAK (dimension = 61)
            $ptkCustomers = DB::connection('axapta_ptk')
                ->table('customer')
                ->where('dimension', '61')
                ->get();

            DB::table('customers_pontianak')->truncate();
            $batchPtk = [];
            foreach ($ptkCustomers as $cust) {
                $batchPtk[] = [
                    'accountnum' => $cust->ACCOUNTNUM ?? null,
                    'name'       => $cust->NAME ?? null,
                    'address'    => $cust->ADDRESS ?? null,
                    'inventsite' => $cust->INVENTSITE ?? null,
                    'agr_school' => $cust->AGR_SCHOOL ?? null,
                    'city'       => $cust->CITY ?? null,
                    'dimension'  => $cust->DIMENSION ?? '61',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($batchPtk) >= 500) {
                    DB::table('customers_pontianak')->insert($batchPtk);
                    $batchPtk = [];
                }
            }
            if (!empty($batchPtk)) {
                DB::table('customers_pontianak')->insert($batchPtk);
            }

            return redirect()->back()->with('success', 'Berhasil menarik seluruh data pelanggan terbaru dari Server AXAPTA (Samarinda & Pontianak)!');

        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Gagal menyambung/menarik data AXAPTA: ' . $e->getMessage());
        }
    }
}