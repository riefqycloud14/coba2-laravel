<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockAnalysisService
{
    /**
     * Mengambil & Mengkalkulasi Seluruh Analisa Stok Lengkap
     */
    public function getAnalysisData()
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        // 1. Ambil Stok Per Item & BU
        $stocks = DB::table('stocks')
            ->select('itemid', 
                DB::raw("SUM(CASE WHEN bu = '59' THEN stok_physical ELSE 0 END) as stok59"),
                DB::raw("SUM(CASE WHEN bu = '61' THEN stok_physical ELSE 0 END) as stok61"),
                DB::raw("SUM(CASE WHEN bu = '59' THEN stok_transit ELSE 0 END) as trn59"),
                DB::raw("SUM(CASE WHEN bu = '61' THEN stok_transit ELSE 0 END) as trn61"),
                DB::raw("SUM(CASE WHEN bu = '59' THEN po_outstanding ELSE 0 END) as poout59"),
                DB::raw("SUM(CASE WHEN bu = '61' THEN po_outstanding ELSE 0 END) as poout61")
            )
            ->groupBy('itemid')
            ->get()
            ->keyBy(fn($item) => trim($item->itemid));

        // 2. Ambil SO Outstanding Per Item & BU
        $soOut = DB::table('sales_order_outstandings')
            ->select('item_id',
                DB::raw("SUM(CASE WHEN bu = 59 AND (sales_responsible LIKE '%BALIKPAPAN%' OR agr_sales_resp_name LIKE '%BALIKPAPAN%') THEN so_eks ELSE 0 END) as sobpp"),
                DB::raw("SUM(CASE WHEN bu = 59 AND (sales_responsible LIKE '%SAMARINDA%' OR agr_sales_resp_name LIKE '%SAMARINDA%' OR sales_responsible IS NULL OR sales_responsible = '') THEN so_eks ELSE 0 END) as sosmd"),
                DB::raw("SUM(CASE WHEN bu = 59 THEN so_eks ELSE 0 END) as sobppsmd"),
                DB::raw("SUM(CASE WHEN bu = 61 THEN so_eks ELSE 0 END) as soptk"),
                DB::raw("SUM(CASE WHEN bu = 59 AND (customer_ref LIKE '%CV%' OR dimension3_ LIKE '%PROYEK%') THEN so_eks ELSE 0 END) as sopryksmd"),
                DB::raw("SUM(CASE WHEN bu = 61 AND (customer_ref LIKE '%CV%' OR dimension3_ LIKE '%PROYEK%') THEN so_eks ELSE 0 END) as soprykptk")
            )
            ->groupBy('item_id')
            ->get()
            ->keyBy(fn($item) => trim($item->item_id));

        // 3. Ambil Realisasi Penjualan & Retur per Item dari Invoices
        $sales = DB::table('invoices')
            ->select('kodbuk',
                DB::raw("SUM(CASE WHEN bu = '59' AND type = 'SALES' THEN kwantum ELSE 0 END) as realsmd"),
                DB::raw("SUM(CASE WHEN bu = '59' AND type = 'RETUR' THEN kwantum ELSE 0 END) as retursmd"),
                DB::raw("SUM(CASE WHEN bu = '61' AND type = 'SALES' THEN kwantum ELSE 0 END) as realptk"),
                DB::raw("SUM(CASE WHEN bu = '61' AND type = 'RETUR' THEN kwantum ELSE 0 END) as returptk")
            )
            ->whereNotNull('kodbuk')
            ->where('kodbuk', '<>', '')
            ->groupBy('kodbuk')
            ->get()
            ->keyBy(fn($item) => trim($item->kodbuk));

        // 4. PERBAIKAN: Ambil Detail Master Buku langsung dari Tabel `items` Lokal
        $itemMeta = DB::table('items')
            ->select(
                'itemid', 
                'itemname', 
                'agr_pengarang', 
                'agr_brandname', 
                'agr_gradename', 
                'agr_itemcurriculum',
                'amount',
                'erl_bidang',
                'erl_nampel',
                'editor_area',
                'grossdepth',
                'grossheight',
                'grosswidth',
                'netweight'
            )
            ->whereNotNull('itemid')
            ->where('itemid', '<>', '')
            ->get()
            ->keyBy(fn($i) => trim($i->itemid));

        // 5. PERBAIKAN: Gabungkan Seluruh Daftar Item (Termasuk dari tabel `items`)
        $allItems = DB::table('items')
            ->select('itemid')
            ->whereNotNull('itemid')
            ->where('itemid', '<>', '')
            ->union(
                DB::table('invoices')
                    ->select('kodbuk as itemid')
                    ->whereNotNull('kodbuk')
                    ->where('kodbuk', '<>', '')
            )
            ->union(
                DB::table('sales_order_outstandings')
                    ->select('item_id as itemid')
                    ->whereNotNull('item_id')
                    ->where('item_id', '<>', '')
            )
            ->union(
                DB::table('stocks')
                    ->select('itemid as itemid')
                    ->whereNotNull('itemid')
                    ->where('itemid', '<>', '')
            )
            ->distinct()
            ->get();

        $analysisResult = [];

        foreach ($allItems as$itemObj) {
            $itemId = trim($itemObj->itemid);
            if (empty($itemId)) continue;

            $stk  =$stocks->get($itemId);$so   = $soOut->get($itemId);
            $sl   =$sales->get($itemId);$meta = $itemMeta->get($itemId);

            $itemName   =$meta->itemname ?? '';
            $pengarang  =$meta->agr_pengarang ?? '';
            $brandName  =$meta->agr_brandname ?? '';
            $gradeName  =$meta->agr_gradename ?? '';
            $curriculum =$meta->agr_itemcurriculum ?? '';
            $price      = (float) ($meta->amount ?? 0);
            $erlBidang  =$meta->erl_bidang ?? '-';
            $erlNampel  =$meta->erl_nampel ?? '-';
            $editorArea =$meta->editor_area ?? '-';

            $grossdepth  = (float) ($meta->grossdepth ?? 0);
            $grossheight = (float) ($meta->grossheight ?? 0);
            $grosswidth  = (float) ($meta->grosswidth ?? 0);
            $netweight   = (float) ($meta->netweight ?? 0);

            $stok59   = (float) ($stk->stok59 ?? 0);
            $stok61   = (float) ($stk->stok61 ?? 0);
            $trn59    = (float) ($stk->trn59 ?? 0);
            $trn61    = (float) ($stk->trn61 ?? 0);
            $poout59  = (float) ($stk->poout59 ?? 0);
            $poout61  = (float) ($stk->poout61 ?? 0);

            $sobpp     = (float) ($so->sobpp ?? 0);
            $sosmd     = (float) ($so->sosmd ?? 0);
            $sobppsmd  = (float) ($so->sobppsmd ?? 0);
            $soptk     = (float) ($so->soptk ?? 0);
            $sopryksmd = (float) ($so->sopryksmd ?? 0);
            $soprykptk = (float) ($so->soprykptk ?? 0);

            $realsmd  = (float) ($sl->realsmd ?? 0);
            $retursmd = (float) ($sl->retursmd ?? 0);
            $realptk  = (float) ($sl->realptk ?? 0);
            $returptk = (float) ($sl->returptk ?? 0);

            $realbpp  = 0; 
            $returbpp = 0;
            $real59   = $realsmd +$realbpp;

            // RUMUS CEK BARTER FOXPRO (SMD <-> PTK)
            $cekbrtptk_smd = ($stok61 + $trn61) -$soptk;
            $cekbrtsmd     =$sobppsmd - ($stok59 +$trn59);

            $ptktosmd = 0;
            $pesan59  = 0;

            if ($sobppsmd > 0) {
                if ($cekbrtsmd > 0 &&$cekbrtptk_smd > 0 && $stok61 > 0) {$ptktosmd = min($cekbrtptk_smd,$sobppsmd - ($stok59 +$trn59));
                } else {
                    $pesan59 = max(0, $sobppsmd - ($stok59 + $trn59 +$poout59));
                }
            }

            $cekbrtptk_ptk =$soptk - ($stok61 +$trn61);
            $cekbrtsmd_ptk = ($stok59 + $trn59) -$sobppsmd;

            $smd2ptk = 0;
            $pesan61 = 0;

            if ($soptk > 0) {
                if ($cekbrtptk_ptk > 0 &&$cekbrtsmd_ptk > 0 && $stok59 > 0) {$smd2ptk = min($cekbrtsmd_ptk,$soptk - ($stok61 +$trn61));
                } else {
                    $pesan61 = max(0, $soptk - ($stok61 + $trn61 +$poout61));
                }
            }

            $gradenUpper = strtoupper(trim($gradeName));
            $bpnbp = in_array($gradenUpper, ['ANAK', 'TK', 'PERTI', 'UMUM']) ? 'NBP' : 'BUPEL';

            $analysisResult[] = [
                'ITEMID'          => $itemId,
                'ITEMNAME'        => $itemName,
                'AGR_PENGAR'      => $pengarang,
                'AGR_ITEMCU'      => $curriculum,
                'ERL_BIDANG'      => $erlBidang,
                'ERL_NAMPEL'      => $erlNampel,
                'AGR_BRANDN'      => $brandName,
                'AGR_GRADEN'      => $gradeName,
                'AMOUNT'          => $price,
                'KOLI'            => 0,
                'GROSSDEPTH'      => $grossdepth,
                'GROSSHEIGH'      => $grossheight,
                'GROSSWIDTH'      => $grosswidth,
                'NETWEIGHT'       => $netweight,
                'EDITOR_ARE'      => $editorArea,
                'BPNBP'           => $bpnbp,
                'REALSMD'         => $realsmd,
                'RETURSMD'        => $retursmd,
                'REALBPP'         => $realbpp,
                'REAL59'          => $real59,
                'RETURBPP'        => $returbpp,
                'SOPRYKBPP'       => 0,
                'SOBPP'           => $sobpp,
                'SOSMD'           => $sosmd,
                'SAWAL59'         => 0,
                'SOPRYKSMD'       => $sopryksmd,
                'SOBPPSMD'        => $sobppsmd,
                'STOK59'          => $stok59,
                'PSI59'           => 0,
                'GRN59'           => 0,
                'PSIWEB59'        => 0,
                'TRN59'           => $trn59,
                'POOUT59'         => $poout59,
                'PESAN59'         => max(0, $pesan59),
                'SMD2PTK'         => max(0, $smd2ptk),
                'POKOLI59'        => 0,
                'KNFIRMSMD'       => '',
                'SAWAL61'         => 0,
                'REALPTK'         => $realptk,
                'RETURPTK'        => $returptk,
                'SOPRYKPTK'       => $soprykptk,
                'SOPTK'           => $soptk,
                'STOK61'          => $stok61,
                'TRN61'           => $trn61,
                'PSI61'           => 0,
                'GRN61'           => 0,
                'PSIWEB61'        => 0,
                'POOUT61'         => $poout61,
                'PESAN61'         => max(0, $pesan61),
                'PTKTOSMD'        => max(0, $ptktosmd),
                'POKOLI61'        => 0,
                'KNFIRMPTK'       => '',
                'SOCAB'           => $sobppsmd +$soptk,
                'STOKCAB'         => $stok59 +$stok61,
                'TRNCAB'          => $trn59 +$trn61,
                'POOUTCAB'        => $poout59 +$poout61,
                'STOKNAS'         => 0,
                'CEKBRTSMD'       => $cekbrtsmd,
                'CEKBRTPTK'       => $cekbrtptk_smd,
                'CEKBRT3'         => 0,
                'KET_PRODUK'      => '-',
                'ED_REVISI'       => '-',
                'ED_KIKD17'       => '-',
                'ED_K13N'         => '-',
            ];
        }

        return collect($analysisResult);
    }

    /**
     * Stream Download CSV Analisa Stok Lengkap
     */
    public function exportCsv(): StreamedResponse
    {
        $filename = 'AnalisaStok_Lengkap_FoxPro_' . date('Y-m-d_H-i-s') . '.csv';
        $data =$this->getAnalysisData();

        return response()->streamDownload(function () use ($data) {$handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");

            // Header Rapi Tanpa Kolom Kosong
            fputcsv($handle, [
                'ITEMID', 'ITEMNAME', 'AGR_PENGAR', 'AGR_ITEMCU', 'ERL_BIDANG', 
                'ERL_NAMPEL', 'AGR_BRANDN', 'AGR_GRADEN', 'AMOUNT', 'KOLI', 'GROSSDEPTH', 
                'GROSSHEIGH', 'GROSSWIDTH', 'NETWEIGHT', 'EDITOR_ARE', 'BPNBP', 'REALSMD', 
                'RETURSMD', 'REALBPP', 'REAL59', 'RETURBPP', 'SOPRYKBPP', 'SOBPP', 
                'SOSMD', 'SAWAL59', 'SOPRYKSMD', 'SOBPPSMD', 'STOK59', 'PSI59', 
                'GRN59', 'PSIWEB59', 'TRN59', 'POOUT59', 'PESAN59', 'SMD2PTK', 
                'POKOLI59', 'KNFIRMSMD', 'SAWAL61', 'REALPTK', 'RETURPTK', 'SOPRYKPTK', 
                'SOPTK', 'STOK61', 'TRN61', 'PSI61', 'GRN61', 'PSIWEB61', 
                'POOUT61', 'PESAN61', 'PTKTOSMD', 'POKOLI61', 'KNFIRMPTK', 'SOCAB', 
                'STOKCAB', 'TRNCAB', 'POOUTCAB', 'STOKNAS', 'CEKBRTSMD', 'CEKBRTPTK', 
                'CEKBRT3', 'KET_PRODUK', 'ED_REVISI', 'ED_KIKD17', 'ED_K13N'
            ]);

            foreach ($data as$row) {
                fputcsv($handle, [
                    '="' . $row['ITEMID'] . '"',
                    $row['ITEMNAME'],
                    $row['AGR_PENGAR'],$row['AGR_ITEMCU'],
                    $row['ERL_BIDANG'],$row['ERL_NAMPEL'],
                    $row['AGR_BRANDN'],$row['AGR_GRADEN'],
                    number_format($row['AMOUNT'], 0, '', ''),$row['KOLI'],
                    $row['GROSSDEPTH'],$row['GROSSHEIGH'],
                    $row['GROSSWIDTH'],$row['NETWEIGHT'],
                    $row['EDITOR_ARE'],$row['BPNBP'],
                    $row['REALSMD'],$row['RETURSMD'],
                    $row['REALBPP'],$row['REAL59'],
                    $row['RETURBPP'],$row['SOPRYKBPP'],
                    $row['SOBPP'],$row['SOSMD'],
                    $row['SAWAL59'],$row['SOPRYKSMD'],
                    $row['SOBPPSMD'],$row['STOK59'],
                    $row['PSI59'],$row['GRN59'],
                    $row['PSIWEB59'],$row['TRN59'],
                    $row['POOUT59'],$row['PESAN59'],
                    $row['SMD2PTK'],$row['POKOLI59'],
                    $row['KNFIRMSMD'],$row['SAWAL61'],
                    $row['REALPTK'],$row['RETURPTK'],
                    $row['SOPRYKPTK'],$row['SOPTK'],
                    $row['STOK61'],$row['TRN61'],
                    $row['PSI61'],$row['GRN61'],
                    $row['PSIWEB61'],$row['POOUT61'],
                    $row['PESAN61'],$row['PTKTOSMD'],
                    $row['POKOLI61'],$row['KNFIRMPTK'],
                    $row['SOCAB'],$row['STOKCAB'],
                    $row['TRNCAB'],$row['POOUTCAB'],
                    $row['STOKNAS'],$row['CEKBRTSMD'],
                    $row['CEKBRTPTK'],$row['CEKBRT3'],
                    $row['KET_PRODUK'],$row['ED_REVISI'],
                    $row['ED_KIKD17'],$row['ED_K13N'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}