<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportDbfCustomers extends Command
{
    protected $signature = 'dbf:import-customers';
    protected $description = 'Import data customer DBF Samarinda dan Pontianak ke tabel MySQL terpisah';

    public function handle()
    {
        $branches = [
            'customers_samarinda' => 'E:/Penjualan/custtableSMD.DBF',
            'customers_pontianak' => 'E:/Penjualan/custtablePTK.DBF',
        ];

        foreach ($branches as $table => $filePath) {
            if (!file_exists($filePath)) {
                $this->warn("File tidak ditemukan, melewati: {$filePath}");
                continue;
            }

            $this->info("Sedang memproses impor data ke tabel: {$table}...");

            $handle = fopen($filePath, 'rb');
            if (!$handle) {
                $this->error("Gagal membuka file: {$filePath}");
                continue;
            }

            // Baca Header Binary DBF (32 Byte pertama)
            $headerData = fread($handle, 32);

            // Extract Byte secara manual agar presisi dan tidak bergantung pada unpack format string
            $recordCount  = unpack('V', substr($headerData, 4, 4))[1]; // 4 byte (32-bit uint)
            $headerLength = unpack('v', substr($headerData, 8, 2))[1]; // 2 byte (16-bit uint)
            $recordLength = unpack('v', substr($headerData, 10, 2))[1]; // 2 byte (16-bit uint)

            // Extract Daftar Kolom dari Header DBF
            $fields = [];
            fseek($handle, 32);
            while (ftell($handle) < $headerLength - 1) {
                $buf = fread($handle, 32);
                $fieldName = strtolower(trim(substr($buf, 0, 11)));
                if (!empty($fieldName)) {
                    $fields[] = [
                        'name' => $fieldName,
                        'len'  => ord($buf[16])
                    ];
                }
            }

            // Kosongkan tabel MySQL sebelum sync ulang
            DB::table($table)->truncate();

            // Pindah pointer ke awal record
            fseek($handle, $headerLength);

            $dataBatch = [];
            for ($i = 0; $i < $recordCount; $i++) {
                $recordBuf = fread($handle, $recordLength);
                if (empty($recordBuf) || strlen($recordBuf) < $recordLength) {
                    continue;
                }

                // Skip jika record terhapus ('*')
                if ($recordBuf[0] === '*') {
                    continue;
                }

                $pos = 1;
                $row = [];

                foreach ($fields as $field) {
                    $val = substr($recordBuf, $pos, $field['len']);
                    $row[$field['name']] = trim(mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1'));
                    $pos += $field['len'];
                }

                $row['created_at'] = now();
                $row['updated_at'] = now();

                $dataBatch[] = $row;

                if (count($dataBatch) >= 500) {
                    DB::table($table)->insert($dataBatch);
                    $dataBatch = [];
                }
            }

            if (!empty($dataBatch)) {
                DB::table($table)->insert($dataBatch);
            }

            fclose($handle);
            $this->info("✓ Berhasil mengimpor data ke tabel {$table}!");
        }

        $this->info('=== Seluruh proses sinkronisasi DBF selesai! ===');
    }
}