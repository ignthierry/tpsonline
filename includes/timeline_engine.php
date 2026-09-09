<?php
/**
 * Engine Alur Operasional Kontainer (Timeline Engine)
 * Modul terpadu untuk menelusuri alur pergerakan kontainer di TPP dan Gudang LCL.
 * Dipakai bersama oleh:
 * - Single Tracking (api/tps_tracking.php)
 * - Batch Tracking (api/tps_tracking_batch.php)
 */

if (!function_exists('getContainerTimelineData')) {
    function getContainerTimelineData(string $noContRaw, string $dept = 'tpp'): array {
        $noContClean = strtoupper(trim(str_replace([' ', '-'], '', $noContRaw)));
        $dept = strtolower(trim($dept));

        if (empty($noContClean)) {
            return ['success' => false, 'message' => 'Nomor kontainer belum diisi'];
        }

        try {
        global $pdo_tpp, $pdo_primamas, $pdo_tpsonline;

        // Helper konversi format tanggal
        $formatDmyHis = function($dtStr) {
            if (empty($dtStr) || $dtStr === '0000-00-00' || $dtStr === '0000-00-00 00:00:00') return '';
            $ts = strtotime($dtStr);
            return $ts ? date('d-m-Y H:i:s', $ts) : '';
        };
        $formatDmy = function($dStr) {
            if (empty($dStr) || $dStr === '0000-00-00' || $dStr === '0000-00-00 00:00:00') return '';
            $ts = strtotime($dStr);
            return $ts ? date('d-m-Y', $ts) : '';
        };

        // 1. Cek status pengiriman ke CEISA dari ceisa_api_logs, ceisa_tps_tracking, dan ceisa_tps_tracking_batch
        $sentKegiatan = [];
        if (!empty($pdo_tpsonline)) {
            try {
                // 1a. Cek dari ceisa_api_logs (mendukung kirim-tps-tracking dan tps-tracking/batch)
                $stmtLogs = $pdo_tpsonline->prepare("
                    SELECT endpoint, request_params, status, http_code, raw_response, created_at 
                    FROM ceisa_api_logs 
                    WHERE endpoint IN ('kirim-tps-tracking', 'tps-tracking/batch') 
                      AND request_params LIKE :q
                      AND (status IN ('SUCCESS', 'CONFLICT') OR http_code = 409 OR raw_response LIKE '%already exists%' OR raw_response LIKE '%sudah pernah%')
                    ORDER BY id DESC
                ");
                $stmtLogs->execute([':q' => "%{$noContClean}%"]);
                $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
                foreach ($logs as $l) {
                    $parsed = json_decode($l['request_params'], true);
                    if (is_array($parsed)) {
                        $itemsList = (isset($parsed[0]) && is_array($parsed[0])) ? $parsed : [$parsed];
                        foreach ($itemsList as $it) {
                            if (!empty($it['nomorKontainer']) && strtoupper(str_replace([' ', '-'], '', (string)$it['nomorKontainer'])) === $noContClean && !empty($it['kodeKegiatan'])) {
                                $k = (int)$it['kodeKegiatan'];
                                $info = [
                                    'sent_at'       => date('d-m-Y H:i', strtotime($l['created_at'])),
                                    'waktuKegiatan' => $it['waktuKegiatan'] ?? '',
                                    'status'        => 'SUCCESS'
                                ];
                                if (!isset($sentKegiatan[$k])) {
                                    $sentKegiatan[$k] = $info;
                                }
                                $bl = trim((string)($it['nomorBlAwb'] ?? ''));
                                if ($bl && !isset($sentKegiatan["{$k}_{$bl}"])) {
                                    $sentKegiatan["{$k}_{$bl}"] = $info;
                                }
                                $dok = trim((string)($it['nomorDokumen'] ?? ''));
                                if ($dok && !isset($sentKegiatan["{$k}_{$dok}"])) {
                                    $sentKegiatan["{$k}_{$dok}"] = $info;
                                }
                            }
                        }
                    }
                }

                // 1b. Cek dari ceisa_tps_tracking & ceisa_tps_tracking_batch (tabel khusus)
                try {
                    $stmtTpsSingle = $pdo_tpsonline->prepare("
                        SELECT kode_kegiatan, waktu_kegiatan, created_at 
                        FROM ceisa_tps_tracking 
                        WHERE no_cont = :c
                        ORDER BY id DESC
                    ");
                    $stmtTpsSingle->execute([':c' => $noContClean]);
                    foreach ($stmtTpsSingle->fetchAll(PDO::FETCH_ASSOC) as $tr) {
                        $kdKeg = (int)$tr['kode_kegiatan'];
                        if ($kdKeg && !isset($sentKegiatan[$kdKeg])) {
                            $sentKegiatan[$kdKeg] = [
                                'sent_at'       => date('d-m-Y H:i', strtotime($tr['created_at'])),
                                'waktuKegiatan' => $tr['waktu_kegiatan'] ?? '',
                                'status'        => 'SUCCESS'
                            ];
                        }
                    }

                    $stmtTpsB = $pdo_tpsonline->prepare("
                        SELECT kode_kegiatan, waktu_kegiatan, created_at 
                        FROM ceisa_tps_tracking_batch 
                        WHERE no_cont = :c
                        ORDER BY id DESC
                    ");
                    $stmtTpsB->execute([':c' => $noContClean]);
                    foreach ($stmtTpsB->fetchAll(PDO::FETCH_ASSOC) as $tr) {
                        $kdKeg = (int)$tr['kode_kegiatan'];
                        if ($kdKeg && !isset($sentKegiatan[$kdKeg])) {
                            $sentKegiatan[$kdKeg] = [
                                'sent_at'       => date('d-m-Y H:i', strtotime($tr['created_at'])),
                                'waktuKegiatan' => $tr['waktu_kegiatan'] ?? '',
                                'status'        => 'SUCCESS'
                            ];
                        }
                    }
                } catch (Exception $eNewTbl) {
                    // Abaikan jika query tabel baru gagal
                }
            } catch (Exception $eLogs) {
                error_log("Error check CEISA logs: " . $eLogs->getMessage());
            }
        }

        if ($dept === 'tpp') {
            // =================================================================
            // ALUR KONTANER TPP (PLP / DATABASE TPP_PRIMAMAS)
            // =================================================================
            if (!$pdo_tpp) {
                return ['success' => false, 'message' => 'Koneksi database TPP tidak tersedia'];
            }

            // 1. Query Kontainer dari tppcontplp
            $sqlCont = "
                SELECT 
                    c.idCont,
                    c.noCont,
                    c.size,
                    c.status,
                    c.type,
                    c.location,
                    c.row,
                    c.slot,
                    c.tier,
                    c.tglInDepo,
                    c.NoPolIn,
                    c.tglGateOutLini1,
                    c.tglOUT_truckingKosong,
                    c.NO_MASTER_BL_AWB,
                    c.TGL_MASTER_BL_AWB,
                    c.NoBC11,
                    c.tglBC11,
                    c.NoPosBC11,
                    c.idPLP_FK,
                    c.ketstatus,
                    c.updateby,
                    c.updateby_name,
                    c.updateby_date,
                    c.shipper,
                    c.stopped,
                    c.SBCF,
                    c.NoBC15,
                    c.tglBC15,
                    p.noPLP,
                    p.tglPLP,
                    p.asalPLP
                FROM tppcontplp c
                LEFT JOIN tppmanifestplp p ON c.idPLP_FK = p.idPLP
                WHERE REPLACE(REPLACE(c.noCont, ' ', ''), '-', '') = :noCont
                ORDER BY c.idCont DESC
                LIMIT 1
            ";
            $stmt = $pdo_tpp->prepare($sqlCont);
            $stmt->execute([':noCont' => $noContClean]);
            $cont = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cont) {
                return [
                    'success' => false,
                    'message' => "Kontainer {$noContClean} tidak ditemukan di database TPP."
                ];
            }

            $idCont = (int)$cont['idCont'];
            $contSize = (strpos($cont['size'], '20') !== false) ? '20' : ((strpos($cont['size'], '45') !== false) ? '45' : '40');
            $contStatus = ($cont['status'] === 'EMPTY') ? '4' : (($cont['status'] === 'LCL') ? '7' : '8');

            // 2. Query Job Behandle dari tppjobplp (Operasional Lapangan Riil) & Fallback ke Invoice Behandle
            $sqlJobBehandle = "
                SELECT j.*
                FROM tppjobplp j
                WHERE j.idCont_FK = :idCont
                  AND j.jobType LIKE '%Behandle%'
                ORDER BY j.idJob DESC
                LIMIT 1
            ";
            $stmtJBe = $pdo_tpp->prepare($sqlJobBehandle);
            $stmtJBe->execute([':idCont' => $idCont]);
            $jobBehandle = $stmtJBe->fetch(PDO::FETCH_ASSOC);

            $sqlBehandle = "
                SELECT inv.*
                FROM tppinvcontplp ic
                JOIN tppinvoiceplp inv ON ic.idInvPLP = inv.idInvPLP
                WHERE ic.idContPLP = :idCont
                  AND (inv.invType LIKE '%behandle%' OR (inv.behandleType IS NOT NULL AND inv.behandleType != ''))
                ORDER BY inv.idInvPLP DESC
                LIMIT 1
            ";
            $stmtBe = $pdo_tpp->prepare($sqlBehandle);
            $stmtBe->execute([':idCont' => $idCont]);
            $behandle = $stmtBe->fetch(PDO::FETCH_ASSOC);

            // 3. Query Log Shifting Lapangan dari container_position_logs (Modul Yard_Position)
            $sqlShiftLog = "
                SELECT l.*
                FROM container_position_logs l
                WHERE (l.idCont_FK = :idCont OR REPLACE(REPLACE(l.container_no, ' ', ''), '-', '') = :noCont)
                  AND (l.move_type LIKE '%Shift%' OR l.move_type LIKE '%Relocate%' 
                       OR (l.from_block IS NOT NULL AND (l.from_block != l.to_block OR l.from_slot != l.to_slot OR l.from_tier != l.to_tier)))
                ORDER BY l.id DESC
                LIMIT 1
            ";
            $stmtShift = $pdo_tpp->prepare($sqlShiftLog);
            $stmtShift->execute([':idCont' => $idCont, ':noCont' => $noContClean]);
            $shiftLog = $stmtShift->fetch(PDO::FETCH_ASSOC);

            // 4. Query Job Stripping dari tppjobplp (Operasional Lapangan Riil) & Fallback ke Invoice Stripping
            $sqlJobStripping = "
                SELECT j.*
                FROM tppjobplp j
                WHERE j.idCont_FK = :idCont
                  AND j.jobType LIKE '%Stripping%'
                ORDER BY j.idJob DESC
                LIMIT 1
            ";
            $stmtJStr = $pdo_tpp->prepare($sqlJobStripping);
            $stmtJStr->execute([':idCont' => $idCont]);
            $jobStripping = $stmtJStr->fetch(PDO::FETCH_ASSOC);

            $sqlStripping = "
                SELECT inv.*
                FROM tppinvcontplp ic
                JOIN tppinvoiceplp inv ON ic.idInvPLP = inv.idInvPLP
                WHERE ic.idContPLP = :idCont
                  AND inv.invType LIKE '%stripping%'
                ORDER BY inv.idInvPLP DESC
                LIMIT 1
            ";
            $stmtStr = $pdo_tpp->prepare($sqlStripping);
            $stmtStr->execute([':idCont' => $idCont]);
            $stripping = $stmtStr->fetch(PDO::FETCH_ASSOC);

            // 5. Query Job Out PLP (Truk Masuk Penjemput) dari tppjobplp
            $sqlJobOut = "
                SELECT j.*
                FROM tppjobplp j
                WHERE j.idCont_FK = :idCont
                  AND j.jobType = 'Job Out PLP'
                ORDER BY j.idJob DESC
                LIMIT 1
            ";
            $stmtJOut = $pdo_tpp->prepare($sqlJobOut);
            $stmtJOut->execute([':idCont' => $idCont]);
            $jobOut = $stmtJOut->fetch(PDO::FETCH_ASSOC);

            // 6. Query Surat Jalan PLP (Truk Penjemput, Pickup & Gate Out)
            $sqlSJ = "
                SELECT *
                FROM tppsuratjalan
                WHERE idManifest = :idCont
                  AND typeManifest = 'PLP'
                ORDER BY idSuratJalan DESC
                LIMIT 1
            ";
            $stmtSJ = $pdo_tpp->prepare($sqlSJ);
            $stmtSJ->execute([':idCont' => $idCont]);
            $sj = $stmtSJ->fetch(PDO::FETCH_ASSOC);

            // 7. Query Invoice Pengeluaran / SPPB Delivery
            $sqlDeliveryInv = "
                SELECT inv.*
                FROM tppinvcontplp ic
                JOIN tppinvoiceplp inv ON ic.idInvPLP = inv.idInvPLP
                WHERE ic.idContPLP = :idCont
                  AND (inv.invType LIKE '%penumpukan%' OR inv.invType LIKE '%storage%' OR (inv.noSPPB IS NOT NULL AND inv.noSPPB != ''))
                ORDER BY 
                    (CASE WHEN inv.jenisSPPB IS NOT NULL AND inv.jenisSPPB != '' THEN 1 ELSE 2 END),
                    inv.idInvPLP DESC
                LIMIT 1
            ";
            $stmtDel = $pdo_tpp->prepare($sqlDeliveryInv);
            $stmtDel->execute([':idCont' => $idCont]);
            $deliveryInv = $stmtDel->fetch(PDO::FETCH_ASSOC);

            // Evaluasi Dokumen Pengeluaran Pabean (Alur 6, 7 & 8) berdasarkan Referensi_Kode_Dokumen_TPS.pdf
            $kodeDokOut = '99'; // Default: 99 = Dokumen Pengeluaran Lainnya
            $namaDokOut = 'Dokumen Pengeluaran Lainnya (Surat Jalan)';
            $noDokOut = $sj ? trim((string)$sj['noSuratJalan']) : '';
            $tglDokOut = $sj ? $formatDmy($sj['tglSuratJalan'] ?: $sj['cetak']) : '';

            $sppbJns = strtoupper(trim((string)($deliveryInv['jenisSPPB'] ?? '')));
            $sppbNo = trim((string)($deliveryInv['noSPPB'] ?? ''));
            $sppbTgl = $formatDmy($deliveryInv['tglSPPB'] ?? null);
            $pibNo = trim((string)($deliveryInv['noPIB'] ?? ''));
            $pibTgl = $formatDmy($deliveryInv['tglPIB'] ?? null);
            $reexpJns = strtoupper(trim((string)($deliveryInv['jenisSurat_reexp'] ?? '')));
            $reexpNo = trim((string)($deliveryInv['noSurat_reexp'] ?? ''));
            $kdDokInv = trim((string)($deliveryInv['kd_Dok'] ?? ''));

            if ($kdDokInv && in_array($kdDokInv, ['1', '2', '3', '4', '8', '9', '10', '13', '14', '20', '21', '26', '28', '35', '40', '41', '44', '64', '99'])) {
                $kodeDokOut = $kdDokInv;
                $noDokOut = $sppbNo ?: ($pibNo ?: $noDokOut);
                $tglDokOut = $sppbTgl ?: ($pibTgl ?: $tglDokOut);
                $namaDokOut = "Dokumen Pabean (Kode {$kodeDokOut})";
            } elseif (strpos($sppbJns, 'RE-EXP') !== false || strpos($reexpJns, 'RE-EXP') !== false || $reexpNo || (stripos($sppbNo, 'KBC') !== false && stripos($sppbNo, 'S-') !== false && ($sppbJns === 'RE-EXP' || strpos($sppbJns, 'EXP') !== false))) {
                // Kode 28: Dokumen BC 1.2 - Re-Ekspor (BC 1.2) Belum aju PIB
                $kodeDokOut = '28';
                $namaDokOut = 'Dokumen BC 1.2 - Re-Ekspor (BC 1.2)';
                $noDokOut = $sppbNo ?: ($reexpNo ?: $noDokOut);
                $tglDokOut = $sppbTgl ?: ($formatDmy($deliveryInv['tglSurat_reexp'] ?? null) ?: $tglDokOut);
            } elseif (strpos($sppbJns, 'BC 2.3') !== false || strpos($sppbJns, 'BC2.3') !== false || strpos($sppbJns, '2.3') !== false) {
                // Kode 2: SPPB BC 2.3 - Dok. SPPB BC 2.3
                $kodeDokOut = '2';
                $namaDokOut = 'SPPB BC 2.3';
                $noDokOut = $sppbNo ?: $noDokOut;
                $tglDokOut = $sppbTgl ?: $tglDokOut;
            } elseif (strpos($sppbJns, 'BC 1.5') !== false || strpos($sppbJns, '1.5') !== false) {
                // Kode 9: BCF 1.5 / Barang Tidak Dikuasai - Container Timbun Lewat Waktu
                $kodeDokOut = '9';
                $namaDokOut = 'BCF 1.5 (Barang Tidak Dikuasai)';
                $noDokOut = $sppbNo ?: $noDokOut;
                $tglDokOut = $sppbTgl ?: $tglDokOut;
            } elseif (strpos($sppbJns, 'BC 1.6') !== false || strpos($sppbJns, 'PLB') !== false) {
                // Kode 41: BC 1.6 - SPPB PLB - BC 1.6
                $kodeDokOut = '41';
                $namaDokOut = 'SPPB PLB - BC 1.6';
                $noDokOut = $sppbNo ?: $noDokOut;
                $tglDokOut = $sppbTgl ?: $tglDokOut;
            } elseif (strpos($sppbJns, 'LELANG') !== false) {
                // Kode 26: Surat Ijin Pengeluaran Barang Untuk Lelang
                $kodeDokOut = '26';
                $namaDokOut = 'Surat Ijin Pengeluaran Barang Untuk Lelang';
                $noDokOut = $sppbNo ?: $noDokOut;
                $tglDokOut = $sppbTgl ?: $tglDokOut;
            } elseif (strpos($sppbJns, 'CARNET') !== false) {
                // Kode 35: ATA CARNET Impor
                $kodeDokOut = '35';
                $namaDokOut = 'ATA CARNET Impor';
                $noDokOut = $sppbNo ?: $noDokOut;
                $tglDokOut = $sppbTgl ?: $tglDokOut;
            } elseif (strpos($sppbJns, 'RETURN') !== false) {
                // Kode 14: Returnable Package - Ijin Impor Returnable Package
                $kodeDokOut = '14';
                $namaDokOut = 'Ijin Impor Returnable Package';
                $noDokOut = $sppbNo ?: $noDokOut;
                $tglDokOut = $sppbTgl ?: $tglDokOut;
            } elseif (strpos($sppbJns, 'PPKEK') !== false) {
                // Kode 64: KEK - Pengeluaran ke KEK/TPB/FTZ
                $kodeDokOut = '64';
                $namaDokOut = 'Pengeluaran KEK';
                $noDokOut = $sppbNo ?: $noDokOut;
                $tglDokOut = $sppbTgl ?: $tglDokOut;
            } elseif ($contStatus === '4' || $cont['status'] === 'EMPTY') {
                // Kode 40: Pengeluaran Empty Container ex. Stripping
                $kodeDokOut = '40';
                $namaDokOut = 'Pengeluaran Empty Container ex. Stripping';
                $noDokOut = $sppbNo ?: ($noSJ ?: $noContClean);
                $tglDokOut = $sppbTgl ?: $tglSJ;
            } elseif (!empty($sppbNo) || !empty($pibNo)) {
                // Kode 1: SPPB BC 2.0 - Dok. SPPB PIB BC 2.0
                $kodeDokOut = '1';
                $namaDokOut = 'SPPB BC 2.0';
                $noDokOut = $sppbNo ?: $pibNo;
                $tglDokOut = $sppbTgl ?: ($pibTgl ?: $tglDokOut);
            }

            // 8. Query Autogate Records dari primamas.gate_log (Koneksi database primamas)
            // Pemetaan alur operasional gerbang fisik:
            // - typeqr = 'A' AND id_gate = 'IN1' : Trailer/truck bawa kontainer PLP masuk depo (Gate In PLP)
            // - typeqr = 'A' AND id_gate = 'OUT1': Trailer/truck kosong keluar depo setelah Stacking Discharge di Yard
            // - typeqr = 'B' AND id_gate = 'IN1' : Trailer/truck kosong masuk depo jemput kontainer pengeluaran (Truck In Lini 2)
            // - Lapangan (antara IN1 & OUT1)     : Kontainer dinaikkan ke atas sasis trailer di yard (Pickup Lini 2)
            // - typeqr = 'B' AND id_gate = 'OUT1': Trailer/truck bawa kontainer keluar depo (Gate Out Lini 2)
            $gateLogA_In = null;
            $gateLogA_Out = null;
            $gateLogB_In = null;
            $gateLogB_Out = null;

            if ($pdo_primamas) {
                try {
                    $stmtGL = $pdo_primamas->prepare("
                        SELECT id, barcode, typeqr, id_gate, date_record, Nopol, status_bc 
                        FROM gate_log 
                        WHERE REPLACE(REPLACE(contno, ' ', ''), '-', '') = :noCont 
                        ORDER BY id ASC
                    ");
                    $stmtGL->execute([':noCont' => $noContClean]);
                    $allGateLogs = $stmtGL->fetchAll(PDO::FETCH_ASSOC);

                    // Saring record sesuai siklus kedatangan kontainer saat ini
                    $tglInTime = !empty($cont['tglInDepo']) ? strtotime($cont['tglInDepo']) : 0;
                    $cycleLogs = [];
                    if ($tglInTime > 0) {
                        $minTime = $tglInTime - (2 * 86400); // toleransi 2 hari sebelum tglInDepo
                        foreach ($allGateLogs as $gl) {
                            if (!empty($gl['date_record']) && strtotime($gl['date_record']) >= $minTime) {
                                $cycleLogs[] = $gl;
                            }
                        }
                    }
                    if (empty($cycleLogs)) {
                        $cycleLogs = $allGateLogs;
                    }

                    foreach ($cycleLogs as $row) {
                        $tqr = strtoupper(trim((string)($row['typeqr'] ?? '')));
                        $gate = strtoupper(trim((string)($row['id_gate'] ?? '')));

                        if ($tqr === 'A' && $gate === 'IN1' && !$gateLogA_In) {
                            $gateLogA_In = $row;
                        } elseif ($tqr === 'A' && $gate === 'OUT1' && !$gateLogA_Out) {
                            $gateLogA_Out = $row;
                        } elseif ($tqr === 'B' && $gate === 'IN1' && !$gateLogB_In) {
                            $gateLogB_In = $row;
                        } elseif ($tqr === 'B' && $gate === 'OUT1' && !$gateLogB_Out) {
                            $gateLogB_Out = $row;
                        }
                    }
                } catch (Exception $eGL) {
                    error_log("Error query primamas.gate_log: " . $eGL->getMessage());
                }
            }

            // Susun Alur Riwayat Kontainer TPP (PLP)
            $timeline = [];

            // -------------------------------------------------------------
            // ALUR 1: GATE IN PLP (IN TRAILER TRUK MASUK) - KODE 5
            // Kondisi primamas.gate_log: typeqr = 'A' AND id_gate = 'IN1'
            // (Trailer/truck membawa kontainer PLP masuk dari Lini 1 ke Depo TPP)
            // -------------------------------------------------------------
            $waktuGateIn = '';
            $nopolIn = '';
            $isGateInAutogate = false;

            if ($gateLogA_In && !empty($gateLogA_In['date_record'])) {
                $waktuGateIn = $formatDmyHis($gateLogA_In['date_record']);
                $nopolIn = trim((string)$gateLogA_In['Nopol']);
                $isGateInAutogate = true;
            }
            if (empty($waktuGateIn) && !empty($cont['tglInDepo'])) {
                $waktuGateIn = $formatDmyHis($cont['tglInDepo']);
            }
            if (empty($nopolIn) && !empty($cont['NoPolIn'])) {
                $nopolIn = trim((string)$cont['NoPolIn']);
            }
            $hasGateIn = !empty($waktuGateIn);

            $noPlpDoc = $cont['noPLP'] ?: ($cont['NoBC11'] ?: '');
            $tglPlpDoc = $formatDmy($cont['tglPLP'] ?: $cont['tglBC11']);
            $noBl = $cont['NO_MASTER_BL_AWB'] ?: '';
            $tglBl = $formatDmy($cont['TGL_MASTER_BL_AWB']);

            // Sanitasi lokasi block untuk OpenAPI CEISA 4.0 (max 10 karakter)
            $yardBlockClean = trim((string)$cont['location']);
            if (strlen($yardBlockClean) > 10) {
                $yardBlockClean = substr($yardBlockClean, 0, 10);
            }
            $yardSlotClean = !empty($cont['slot']) ? trim((string)$cont['slot']) : '1';
            $yardTierClean = !empty($cont['tier']) ? trim((string)$cont['tier']) : '1';

            $payload1 = null;
            if ($hasGateIn) {
                $payload1 = [
                    'departemen'      => 'TPP',
                    'nomorKontainer'  => $noContClean,
                    'ukuranKontainer' => $contSize,
                    'jenisKontainer'  => $contStatus,
                    'kodeTps'         => env('TPS_KODE', 'PSU0'),
                    'kodeGudang'      => env('GUDANG_KODE_CPSU', 'CPSU'),
                    'kodeKegiatan'    => 5,
                    'waktuKegiatan'   => $waktuGateIn
                ];
                if ($nopolIn) $payload1['nomorPolisi'] = $nopolIn;
                if ($noPlpDoc) {
                    $payload1['kodeDokumen'] = '3';
                    $payload1['nomorDokumen'] = $noPlpDoc;
                    if ($tglPlpDoc) $payload1['tanggalDokumen'] = $tglPlpDoc;
                }
                if ($noBl) {
                    $payload1['nomorBlAwb'] = $noBl;
                    if ($tglBl) $payload1['tanggalBlAwb'] = $tglBl;
                }
            }

            $deskripsi1 = $hasGateIn
                ? ($isGateInAutogate
                    ? "Trailer membawa kontainer PLP masuk depo via Autogate (Scan Type A IN1" . ($nopolIn ? ", Nopol: {$nopolIn}" : "") . ")"
                    : "Truk masuk membawa kontainer dari Lini 1 ke Depo TPP (Waktu In Depo: {$waktuGateIn})")
                : 'Kontainer belum masuk / tglInDepo kosong';

            $timeline[] = [
                'step'            => 1,
                'kodeKegiatan'    => 5,
                'kegiatanLabel'   => 'Gate In PLP (In Trailer Truk Masuk)',
                'icon'            => '🚚',
                'badgeCategory'   => 'Masuk Depo',
                'available'       => $hasGateIn,
                'waktuKegiatan'   => $waktuGateIn ?: '-',
                'nomorPolisi'     => $nopolIn,
                'nopolLabel'      => $nopolIn ? "In Trailer: {$nopolIn}" : '-',
                'kodeDokumen'     => '3',
                'namaDokumen'     => 'PLP - Dok. PLP/OB (A11)',
                'dokumenLabel'    => $noPlpDoc ? "PLP (Kode 3): {$noPlpDoc}" : '-',
                'lokasiYard'      => '-',
                'deskripsi'       => $deskripsi1,
                'is_sent'         => isset($sentKegiatan[5]),
                'sent_info'       => $sentKegiatan[5] ?? null,
                'payload'         => $payload1
            ];

            // -------------------------------------------------------------
            // ALUR 2: STACKING DISCHARGE LINI 2 (PENUMPUKAN YARD) - KODE 17
            // Sumber data:
            // 1. primamas.gate_log: typeqr = 'A' AND id_gate = 'OUT1' (Trailer kosong keluar setelah stacking selesai)
            // 2. tppcontplp.tglOUT_truckingKosong: waktu truk pengantar keluar kosong setelah menurunkan kontainer di yard
            // Catatan: Gate In PLP SELALU LEBIH DULU dibanding Stacking Discharge Lapangan!
            // -------------------------------------------------------------
            $hasStacking = !empty($yardBlockClean);
            $waktuStacking = '';
            $isStackingAutogate = false;

            if ($gateLogA_Out && !empty($gateLogA_Out['date_record'])) {
                $waktuStacking = $formatDmyHis($gateLogA_Out['date_record']);
                $isStackingAutogate = true;
            } elseif (!empty($cont['tglOUT_truckingKosong']) && $cont['tglOUT_truckingKosong'] !== '0000-00-00 00:00:00') {
                $waktuStacking = $formatDmyHis($cont['tglOUT_truckingKosong']);
            } elseif ($hasGateIn) {
                // Jika tglOUT_truckingKosong kosong, beri estimasi jeda realistis 5 menit setelah Gate In
                $tIn = strtotime($waktuGateIn);
                $waktuStacking = $tIn ? date('d-m-Y H:i:s', $tIn + 300) : $waktuGateIn;
            } else {
                $waktuStacking = date('d-m-Y H:i:s');
            }

            $payload2 = null;
            if ($hasStacking) {
                $payload2 = [
                    'departemen'      => 'TPP',
                    'nomorKontainer'  => $noContClean,
                    'ukuranKontainer' => $contSize,
                    'jenisKontainer'  => $contStatus,
                    'kodeTps'         => env('TPS_KODE', 'PSU0'),
                    'kodeGudang'      => env('GUDANG_KODE_CPSU', 'CPSU'),
                    'kodeKegiatan'    => 17,
                    'waktuKegiatan'   => $waktuStacking,
                    'block'           => $yardBlockClean,
                    'slot'            => $yardSlotClean,
                    'tier'            => $yardTierClean
                ];
                if ($noPlpDoc) {
                    $payload2['kodeDokumen'] = '3';
                    $payload2['nomorDokumen'] = $noPlpDoc;
                    if ($tglPlpDoc) $payload2['tanggalDokumen'] = $tglPlpDoc;
                }
                if ($noBl) {
                    $payload2['nomorBlAwb'] = $noBl;
                    if ($tglBl) $payload2['tanggalBlAwb'] = $tglBl;
                }
            }

            $deskripsi2 = $hasStacking
                ? ($isStackingAutogate
                    ? "Penumpukan di yard (" . ($cont['location'] ?: 'Yard Lapangan') . ") selesai & trailer keluar kosong (Scan Type A OUT1)"
                    : (!empty($cont['tglOUT_truckingKosong']) && $cont['tglOUT_truckingKosong'] !== '0000-00-00 00:00:00'
                        ? "Penumpukan di yard (" . ($cont['location'] ?: 'Yard Lapangan') . ") selesai & trailer keluar kosong (tglOUT_truckingKosong)"
                        : "Penempatan kontainer di yard depo (" . ($cont['location'] ?: 'Yard Lapangan') . ")"))
                : 'Lokasi yard belum ditentukan';

            $timeline[] = [
                'step'            => 2,
                'kodeKegiatan'    => 17,
                'kegiatanLabel'   => 'Stacking Discharge Lapangan (Yard)',
                'icon'            => '🏗️',
                'badgeCategory'   => 'Yard Stacking',
                'available'       => $hasStacking,
                'waktuKegiatan'   => $hasStacking ? $waktuStacking : '-',
                'nomorPolisi'     => '',
                'nopolLabel'      => '-',
                'kodeDokumen'     => '3',
                'namaDokumen'     => 'PLP - Dok. PLP/OB (A11)',
                'dokumenLabel'    => $noPlpDoc ? "PLP (Kode 3): {$noPlpDoc}" : '-',
                'lokasiYard'      => $yardBlockClean ?: '-',
                'deskripsi'       => $deskripsi2,
                'is_sent'         => isset($sentKegiatan[17]),
                'sent_info'       => $sentKegiatan[17] ?? null,
                'payload'         => $payload2
            ];

            // -------------------------------------------------------------
            // ALUR 3: BEHANDLE LINI 2 (PEMERIKSAAN FISIK) - KODE 21
            // Waktu kegiatan diambil dari saat Job Behandle dibuat (tglJob)
            // -------------------------------------------------------------
            $hasBehandle = false;
            $waktuBehandle = '';
            $docBehandle = $noPlpDoc;
            $tglDocBehandle = $tglPlpDoc;
            $kdDokBehandle = '3';
            $petugasBehandle = '';

            if ($jobBehandle && !empty($jobBehandle['tglJob'])) {
                $hasBehandle = true;
                $waktuBehandle = $formatDmyHis($jobBehandle['tglJob']);
                $petugasBehandle = trim((string)($jobBehandle['operator'] ?: ($jobBehandle['user'] ?: 'Petugas Lapangan')));
            } elseif ($behandle) {
                // Fallback jika hanya ada rekaman invoice behandle
                $hasBehandle = true;
                $waktuBehandle = $formatDmyHis($behandle['inputTime'] ?: ($behandle['tglInvPLP'] . ' 09:00:00'));
                $docBehandle = $behandle['noSPPB'] ?: ($behandle['noInvPLP'] ?: $noPlpDoc);
                $tglDocBehandle = $formatDmy($behandle['tglSPPB'] ?: $behandle['tglInvPLP']) ?: $tglPlpDoc;
                $kdDokBehandle = $behandle['noSPPB'] ? '8' : '3';
            } elseif (!empty($cont['updateby_date']) && $cont['updateby_date'] !== '0000-00-00 00:00:00') {
                $hasBehandle = true;
                $waktuBehandle = $formatDmyHis($cont['updateby_date']);
                $petugasBehandle = trim((string)($cont['updateby_name'] ?: ($cont['updateby'] ?: 'P2')));
            }

            $payload3 = null;
            if ($hasBehandle) {
                $payload3 = [
                    'departemen'      => 'TPP',
                    'nomorKontainer'  => $noContClean,
                    'ukuranKontainer' => $contSize,
                    'jenisKontainer'  => $contStatus,
                    'kodeTps'         => env('TPS_KODE', 'PSU0'),
                    'kodeGudang'      => env('GUDANG_KODE_CPSU', 'CPSU'),
                    'kodeKegiatan'    => 21,
                    'waktuKegiatan'   => $waktuBehandle,
                    'block'           => $yardBlockClean ?: 'BLOK BHD',
                    'slot'            => $yardSlotClean ?: '1',
                    'tier'            => $yardTierClean ?: '1',
                    'kodeDokumen'     => $kdDokBehandle,
                    'nomorDokumen'    => $docBehandle ?: $noPlpDoc
                ];
                if ($tglDocBehandle) $payload3['tanggalDokumen'] = $tglDocBehandle;
                if ($noBl) {
                    $payload3['nomorBlAwb'] = $noBl;
                    if ($tglBl) $payload3['tanggalBlAwb'] = $tglBl;
                }
            }

            $timeline[] = [
                'step'            => 3,
                'kodeKegiatan'    => 21,
                'kegiatanLabel'   => 'Behandle Lini 2 (Pemeriksaan Fisik)',
                'icon'            => '🔍',
                'badgeCategory'   => 'Pemeriksaan Pabean',
                'available'       => $hasBehandle,
                'waktuKegiatan'   => $waktuBehandle ?: '-',
                'nomorPolisi'     => '',
                'nopolLabel'      => '-',
                'kodeDokumen'     => $kdDokBehandle,
                'namaDokumen'     => $kdDokBehandle === '8' ? 'PPB - Dok. Periksa Fisik' : 'PLP - Dok. PLP/OB (A11)',
                'dokumenLabel'    => $docBehandle ? "PLP/SPJM: {$docBehandle}" : ($noPlpDoc ? "PLP: {$noPlpDoc}" : '-'),
                'lokasiYard'      => $yardBlockClean ?: '-',
                'deskripsi'       => $hasBehandle ? ("Pemeriksaan fisik pabean / Behandle" . ($petugasBehandle ? " (Pelaksana: {$petugasBehandle})" : "")) : 'Pemeriksaan fisik belum dilaksanakan / tidak ada Job Behandle',
                'is_sent'         => isset($sentKegiatan[21]),
                'sent_info'       => $sentKegiatan[21] ?? null,
                'payload'         => $payload3
            ];

            // -------------------------------------------------------------
            // ALUR 4: SHIFTING LINI 2 (PERGESERAN POSISI YARD) - KODE 22
            // Membaca log audit container_position_logs dari modul Yard_Position
            // -------------------------------------------------------------
            $hasShifting = !empty($shiftLog);
            $waktuShifting = $hasShifting ? $formatDmyHis($shiftLog['created_at']) : '';
            $shiftBlock = $hasShifting ? trim((string)$shiftLog['to_block']) : '';
            if (strlen($shiftBlock) > 10) {
                $shiftBlock = substr($shiftBlock, 0, 10);
            }
            $shiftSlot = $hasShifting && !empty($shiftLog['to_slot']) ? trim((string)$shiftLog['to_slot']) : '1';
            $shiftTier = $hasShifting && !empty($shiftLog['to_tier']) ? trim((string)$shiftLog['to_tier']) : '1';
            $fromBlockDesc = $hasShifting ? trim((string)($shiftLog['from_block'] ?: '-')) : '';
            $toBlockDesc = $hasShifting ? trim((string)($shiftLog['to_block'] ?: '-')) : '';
            $operatorShift = $hasShifting ? trim((string)($shiftLog['operator_id'] ?: 'Krani Lapangan')) : '';

            $payload4 = null;
            if ($hasShifting) {
                $payload4 = [
                    'departemen'      => 'TPP',
                    'nomorKontainer'  => $noContClean,
                    'ukuranKontainer' => $contSize,
                    'jenisKontainer'  => $contStatus,
                    'kodeTps'         => env('TPS_KODE', 'PSU0'),
                    'kodeGudang'      => env('GUDANG_KODE_CPSU', 'CPSU'),
                    'kodeKegiatan'    => 22,
                    'waktuKegiatan'   => $waktuShifting,
                    'block'           => $shiftBlock ?: ($yardBlockClean ?: 'YARD'),
                    'slot'            => $shiftSlot ?: '1',
                    'tier'            => $shiftTier ?: '1',
                    'kodeDokumen'     => '3',
                    'nomorDokumen'    => $noPlpDoc
                ];
                if ($tglPlpDoc) $payload4['tanggalDokumen'] = $tglPlpDoc;
                if ($noBl) {
                    $payload4['nomorBlAwb'] = $noBl;
                    if ($tglBl) $payload4['tanggalBlAwb'] = $tglBl;
                }
            }

            $timeline[] = [
                'step'            => 4,
                'kodeKegiatan'    => 22,
                'kegiatanLabel'   => 'Shifting Lini 2 (Pergeseran Posisi Yard)',
                'icon'            => '🔄',
                'badgeCategory'   => 'Relokasi Yard',
                'available'       => $hasShifting,
                'waktuKegiatan'   => $waktuShifting ?: '-',
                'nomorPolisi'     => '',
                'nopolLabel'      => '-',
                'kodeDokumen'     => '3',
                'namaDokumen'     => 'PLP - Dok. PLP/OB (A11)',
                'dokumenLabel'    => $noPlpDoc ? "PLP (Kode 3): {$noPlpDoc}" : '-',
                'lokasiYard'      => $shiftBlock ?: ($yardBlockClean ?: '-'),
                'deskripsi'       => $hasShifting ? "Pergeseran posisi kontainer: {$fromBlockDesc} ➜ {$toBlockDesc} (Operator: {$operatorShift})" : 'Tidak ada riwayat pergeseran/shifting posisi di yard',
                'is_sent'         => isset($sentKegiatan[22]),
                'sent_info'       => $sentKegiatan[22] ?? null,
                'payload'         => $payload4
            ];

            // -------------------------------------------------------------
            // ALUR 5: STRIPPING STUFFING LINI 2 (BONGKAR KARGO) - KODE 23
            // Waktu kegiatan diambil dari saat Job Stripping dibuat (tglJob)
            // -------------------------------------------------------------
            $hasStripping = false;
            $waktuStripping = '';
            $docStripping = '';
            $tglDocStripping = '';
            $petugasStripping = '';

            if ($jobStripping && !empty($jobStripping['tglJob'])) {
                $hasStripping = true;
                $waktuStripping = $formatDmyHis($jobStripping['tglJob']);
                $docStripping = $noPlpDoc;
                $tglDocStripping = $tglPlpDoc;
                $petugasStripping = trim((string)($jobStripping['operator'] ?: ($jobStripping['user'] ?: 'Petugas Lapangan')));
            } elseif ($stripping) {
                $hasStripping = true;
                $waktuStripping = $formatDmyHis($stripping['inputTime'] ?: ($stripping['tglInvPLP'] . ' 10:00:00'));
                $docStripping = $stripping['noSPPB'] ?: ($stripping['noInvPLP'] ?: $noPlpDoc);
                $tglDocStripping = $formatDmy($stripping['tglSPPB'] ?: $stripping['tglInvPLP']) ?: $tglPlpDoc;
            }

            $payload5 = null;
            if ($hasStripping) {
                $payload5 = [
                    'departemen'      => 'TPP',
                    'nomorKontainer'  => $noContClean,
                    'ukuranKontainer' => $contSize,
                    'jenisKontainer'  => $contStatus,
                    'kodeTps'         => env('TPS_KODE', 'PSU0'),
                    'kodeGudang'      => env('GUDANG_KODE_CPSU', 'CPSU'),
                    'kodeKegiatan'    => 23,
                    'waktuKegiatan'   => $waktuStripping,
                    'kodeDokumen'     => '3',
                    'nomorDokumen'    => $docStripping ?: $noPlpDoc
                ];
                if ($tglDocStripping) $payload5['tanggalDokumen'] = $tglDocStripping;
                if ($noBl) {
                    $payload5['nomorBlAwb'] = $noBl;
                    if ($tglBl) $payload5['tanggalBlAwb'] = $tglBl;
                }
            }

            $cargoNote = $stripping ? trim(($stripping['jumlah'] ? $stripping['jumlah'] . ' ' . $stripping['satuan'] : '') . ' ' . ($stripping['namaBarang'] ?? '')) : '';

            $timeline[] = [
                'step'            => 5,
                'kodeKegiatan'    => 23,
                'kegiatanLabel'   => 'Stripping Stuffing Lini 2 (Bongkar Kargo)',
                'icon'            => '📦',
                'badgeCategory'   => 'Stripping Depo',
                'available'       => $hasStripping,
                'waktuKegiatan'   => $waktuStripping ?: '-',
                'nomorPolisi'     => '',
                'nopolLabel'      => '-',
                'kodeDokumen'     => '3',
                'namaDokumen'     => 'PLP - Dok. PLP/OB (A11)',
                'dokumenLabel'    => $docStripping ? "PLP/Stripping: {$docStripping}" : ($noPlpDoc ? "PLP: {$noPlpDoc}" : '-'),
                'lokasiYard'      => '-',
                'deskripsi'       => $hasStripping ? ("Pembongkaran muatan kargo di depo" . ($petugasStripping ? " (Pelaksana: {$petugasStripping})" : ($cargoNote ? " ({$cargoNote})" : ''))) : 'Bongkar kargo belum dilaksanakan / tidak ada Job Stripping',
                'is_sent'         => isset($sentKegiatan[23]),
                'sent_info'       => $sentKegiatan[23] ?? null,
                'payload'         => $payload5
            ];

            // -------------------------------------------------------------
            // ALUR 6: TRUCK IN LINI 2 (TRUK PENJEMPUT MASUK) - KODE 19
            // Sumber: primamas.gate_log dengan typeqr = 'B' AND id_gate = 'IN1'
            // (Truk trailer kosong masuk gerbang depo untuk menjemput kontainer)
            // -------------------------------------------------------------
            $waktuTruckIn = '';
            $nopolTruckIn = '';
            $hasTruckIn = false;
            $noSJ = $sj ? trim((string)$sj['noSuratJalan']) : '';

            if ($gateLogB_In && !empty($gateLogB_In['date_record'])) {
                $hasTruckIn = true;
                $waktuTruckIn = $formatDmyHis($gateLogB_In['date_record']);
                $nopolTruckIn = trim((string)$gateLogB_In['Nopol']);
            }
            if (empty($nopolTruckIn) && $sj && !empty($sj['noPol'])) {
                $nopolTruckIn = trim((string)$sj['noPol']);
            }

            $payload6 = null;
            if ($hasTruckIn) {
                $payload6 = [
                    'departemen'      => 'TPP',
                    'nomorKontainer'  => $noContClean,
                    'ukuranKontainer' => $contSize,
                    'jenisKontainer'  => $contStatus,
                    'kodeTps'         => env('TPS_KODE', 'PSU0'),
                    'kodeGudang'      => env('GUDANG_KODE_CPSU', 'CPSU'),
                    'kodeKegiatan'    => 19, // 19 = TRUCK IN LINI 2
                    'waktuKegiatan'   => $waktuTruckIn
                ];
                if ($nopolTruckIn) $payload6['nomorPolisi'] = $nopolTruckIn;
                if ($noDokOut) {
                    $payload6['kodeDokumen'] = $kodeDokOut;
                    $payload6['nomorDokumen'] = $noDokOut;
                    if ($tglDokOut) $payload6['tanggalDokumen'] = $tglDokOut;
                }
                if ($noBl) {
                    $payload6['nomorBlAwb'] = $noBl;
                    if ($tglBl) $payload6['tanggalBlAwb'] = $tglBl;
                }
            }

            $deskripsi6 = $hasTruckIn
                ? ("Trailer kosong masuk depo untuk penjemputan kontainer via Autogate (Scan Type B IN1" . ($nopolTruckIn ? ", Nopol: {$nopolTruckIn}" : "") . ")")
                : 'Belum ada catatan truk penjemput masuk via Autogate (Scan Type B IN1)';

            $timeline[] = [
                'step'            => 6,
                'kodeKegiatan'    => 19,
                'kegiatanLabel'   => 'Truck In Lini 2 (Truk Masuk Penjemput)',
                'icon'            => '🚛',
                'badgeCategory'   => 'Armada Penjemput',
                'available'       => $hasTruckIn,
                'waktuKegiatan'   => $waktuTruckIn ?: '-',
                'nomorPolisi'     => $nopolTruckIn,
                'nopolLabel'      => $nopolTruckIn ? "Truk Penjemput: {$nopolTruckIn}" : '-',
                'kodeDokumen'     => $kodeDokOut,
                'namaDokumen'     => $namaDokOut,
                'dokumenLabel'    => $noDokOut ? "{$namaDokOut} [Kode {$kodeDokOut}]: {$noDokOut}" : ($noSJ ? "SJ: {$noSJ}" : '-'),
                'lokasiYard'      => '-', // Truk baru masuk pintu gerbang depo
                'deskripsi'       => $deskripsi6,
                'is_sent'         => isset($sentKegiatan[19]),
                'sent_info'       => $sentKegiatan[19] ?? null,
                'payload'         => $payload6
            ];

            // -------------------------------------------------------------
            // ALUR 7: PICKUP LINI 2 (KONTAINER DIANGKAT KE SASIS TRUK) - KODE 20
            // Sumber: Surat Jalan untuk PLP (tppsuratjalan)
            // (Waktu saat kontainer dinaikkan ke sasis truk di yard & Surat Jalan diterbitkan)
            // Posisi yard WAJIB KOSONG/NULL sesuai standar CEISA 4.0 Bab 8.2
            // -------------------------------------------------------------
            $waktuPickup = '';
            $hasPickup = false;
            $nopolPickup = $sj ? trim((string)$sj['noPol']) : ($nopolTruckIn ?: '');

            if ($sj && (!empty($sj['tglIN_truckingKosong']) || !empty($sj['tglSuratJalan']) || !empty($sj['cetak']))) {
                $hasPickup = true;
                $waktuPickup = $formatDmyHis($sj['tglIN_truckingKosong'] ?: ($sj['tglSuratJalan'] ?: $sj['cetak']));
            }

            $payload7 = null;
            if ($hasPickup) {
                $payload7 = [
                    'departemen'      => 'TPP',
                    'nomorKontainer'  => $noContClean,
                    'ukuranKontainer' => $contSize,
                    'jenisKontainer'  => $contStatus,
                    'kodeTps'         => env('TPS_KODE', 'PSU0'),
                    'kodeGudang'      => env('GUDANG_KODE_CPSU', 'CPSU'),
                    'kodeKegiatan'    => 20, // 20 = PICKUP LINI 2
                    'waktuKegiatan'   => $waktuPickup
                ];
                if ($nopolPickup) $payload7['nomorPolisi'] = $nopolPickup;
                if ($noDokOut) {
                    $payload7['kodeDokumen'] = $kodeDokOut;
                    $payload7['nomorDokumen'] = $noDokOut;
                    if ($tglDokOut) $payload7['tanggalDokumen'] = $tglDokOut;
                }
                if ($noBl) {
                    $payload7['nomorBlAwb'] = $noBl;
                    if ($tglBl) $payload7['tanggalBlAwb'] = $tglBl;
                }
            }

            $timeline[] = [
                'step'            => 7,
                'kodeKegiatan'    => 20,
                'kegiatanLabel'   => 'Pickup Lini 2 (Kontainer Naik ke Sasis Truk)',
                'icon'            => '🏗️',
                'badgeCategory'   => 'Lift On / Pickup',
                'available'       => $hasPickup,
                'waktuKegiatan'   => $waktuPickup ?: '-',
                'nomorPolisi'     => $nopolPickup,
                'nopolLabel'      => $nopolPickup ? "Armada: {$nopolPickup}" : '-',
                'kodeDokumen'     => $kodeDokOut,
                'namaDokumen'     => $namaDokOut,
                'dokumenLabel'    => $noDokOut ? "{$namaDokOut} [Kode {$kodeDokOut}]: {$noDokOut}" : ($noSJ ? "SJ: {$noSJ}" : '-'),
                'lokasiYard'      => '-', // Posisi yard WAJIB NULL (kontainer telah diangkat meninggalkan yard)
                'deskripsi'       => $hasPickup ? ("Kontainer dinaikkan ke sasis truk di yard & Surat Jalan terbit (" . ($noSJ ?: 'SJ PLP') . ")") : 'Surat Jalan PLP belum terbit / kontainer belum di-pickup',
                'is_sent'         => isset($sentKegiatan[20]),
                'sent_info'       => $sentKegiatan[20] ?? null,
                'payload'         => $payload7
            ];

            // -------------------------------------------------------------
            // -------------------------------------------------------------
            // ALUR 8: GATE OUT LINI 2 (OUT TRAILER TRUK KELUAR) - KODE 6
            // Sumber: primamas.gate_log dengan typeqr = 'B' AND id_gate = 'OUT1'
            // (Trailer/truck membawa kontainer keluar meninggalkan Depo TPP)
            // Posisi yard WAJIB KOSONG/NULL
            // -------------------------------------------------------------
            $waktuGateOut = '';
            $nopolGateOut = '';
            $hasGateOut = false;

            if ($gateLogB_Out && !empty($gateLogB_Out['date_record'])) {
                $hasGateOut = true;
                $waktuGateOut = $formatDmyHis($gateLogB_Out['date_record']);
                $nopolGateOut = trim((string)$gateLogB_Out['Nopol']);
            }
            if (empty($nopolGateOut) && $sj && !empty($sj['noPol'])) {
                $nopolGateOut = trim((string)$sj['noPol']);
            }

            $payload8 = null;
            if ($hasGateOut) {
                $payload8 = [
                    'departemen'      => 'TPP',
                    'nomorKontainer'  => $noContClean,
                    'ukuranKontainer' => $contSize,
                    'jenisKontainer'  => $contStatus,
                    'kodeTps'         => env('TPS_KODE', 'PSU0'),
                    'kodeGudang'      => env('GUDANG_KODE_CPSU', 'CPSU'),
                    'kodeKegiatan'    => 6, // 6 = GATE OUT LINI 2
                    'waktuKegiatan'   => $waktuGateOut
                ];
                if ($nopolGateOut) $payload8['nomorPolisi'] = $nopolGateOut;
                if ($noDokOut) {
                    $payload8['kodeDokumen'] = $kodeDokOut;
                    $payload8['nomorDokumen'] = $noDokOut;
                    if ($tglDokOut) $payload8['tanggalDokumen'] = $tglDokOut;
                }
                if ($noBl) {
                    $payload8['nomorBlAwb'] = $noBl;
                    if ($tglBl) $payload8['tanggalBlAwb'] = $tglBl;
                }
            }

            $deskripsi8 = $hasGateOut
                ? ("Trailer membawa kontainer keluar meninggalkan depo via Autogate (Scan Type B OUT1" . ($nopolGateOut ? ", Nopol: {$nopolGateOut}" : "") . ")")
                : 'Belum ada catatan truk keluar membawa kontainer via Autogate (Scan Type B OUT1)';

            $timeline[] = [
                'step'            => 8,
                'kodeKegiatan'    => 6,
                'kegiatanLabel'   => 'Gate Out Lini 2 (Out Trailer Truk Keluar)',
                'icon'            => '🚚',
                'badgeCategory'   => 'Keluar Depo',
                'available'       => $hasGateOut,
                'waktuKegiatan'   => $waktuGateOut ?: '-',
                'nomorPolisi'     => $nopolGateOut,
                'nopolLabel'      => $nopolGateOut ? "Out Trailer: {$nopolGateOut}" : '-',
                'kodeDokumen'     => $kodeDokOut,
                'namaDokumen'     => $namaDokOut,
                'dokumenLabel'    => $noDokOut ? "{$namaDokOut} [Kode {$kodeDokOut}]: {$noDokOut}" : ($noSJ ? "SJ: {$noSJ}" : '-'),
                'lokasiYard'      => '-', // Posisi yard WAJIB NULL
                'deskripsi'       => $deskripsi8,
                'is_sent'         => isset($sentKegiatan[6]),
                'sent_info'       => $sentKegiatan[6] ?? null,
                'payload'         => $payload8
            ];

            // Urutkan riwayat alur operasional secara KRONOLOGIS berdasarkan waktuKegiatan sesungguhnya
            $availSteps = [];
            $unavailSteps = [];
            foreach ($timeline as $st) {
                if ($st['available'] && !empty($st['payload'])) {
                    $availSteps[] = $st;
                } else {
                    $unavailSteps[] = $st;
                }
            }

            $priorityOrder = [
                5  => 1, // Gate In PLP
                17 => 2, // Stacking Yard
                21 => 3, // Behandle
                23 => 4, // Stripping
                22 => 5, // Shifting
                19 => 6, // Truck In
                20 => 7, // Pickup
                6  => 8  // Gate Out
            ];

            usort($availSteps, function($a, $b) use ($priorityOrder) {
                $timeA = 0;
                $timeB = 0;
                if (!empty($a['waktuKegiatan']) && $a['waktuKegiatan'] !== '-') {
                    $dA = DateTime::createFromFormat('d-m-Y H:i:s', $a['waktuKegiatan']);
                    $timeA = $dA ? $dA->getTimestamp() : strtotime($a['waktuKegiatan']);
                }
                if (!empty($b['waktuKegiatan']) && $b['waktuKegiatan'] !== '-') {
                    $dB = DateTime::createFromFormat('d-m-Y H:i:s', $b['waktuKegiatan']);
                    $timeB = $dB ? $dB->getTimestamp() : strtotime($b['waktuKegiatan']);
                }

                if ($timeA !== $timeB) {
                    return $timeA <=> $timeB;
                }

                $pA = $priorityOrder[$a['kodeKegiatan']] ?? 99;
                $pB = $priorityOrder[$b['kodeKegiatan']] ?? 99;
                return $pA <=> $pB;
            });

            // Susun ulang nomor urut step sesuai urutan kronologis riil
            $sortedTimeline = [];
            $stepNum = 1;
            foreach ($availSteps as $st) {
                $st['step'] = $stepNum++;
                $sortedTimeline[] = $st;
            }
            foreach ($unavailSteps as $st) {
                $st['step'] = $stepNum++;
                $sortedTimeline[] = $st;
            }
            $timeline = $sortedTimeline;

            return [
                'success'    => true,
                'departemen' => 'TPP',
                'container'  => [
                    'nomorKontainer'  => $noContClean,
                    'ukuranKontainer' => $contSize,
                    'jenisKontainer'  => $contStatus,
                    'statusKontainer' => $cont['status'] ?: 'FCL',
                    'lokasiYard'      => $cont['location'] ?: '-',
                    'shipper'         => $cont['shipper'] ?: '-',
                    'inTrailer'       => $nopolIn ?: '-',
                    'outTrailer'      => $nopolGateOut ?: ($nopolPickup ?: ($nopolTruckIn ?: '-')),
                    'suratPlp'        => $noPlpDoc ?: '-',
                    'noBl'            => $noBl ?: '-',
                    'dokumenPengeluaran' => ($noDokOut ? "{$namaDokOut} ({$noDokOut})" : '-')
                ],
                'timeline'   => $timeline
            ];

        } else {
            // =================================================================
            // ALUR KONTAINER GUDANG (LCL / DATABASE PRIMAMAS)
            // =================================================================
            if (!$pdo_primamas) {
                return ['success' => false, 'message' => 'Koneksi database Gudang (primamas) tidak tersedia'];
            }

            $sqlGudang = "
                SELECT 
                    m.Id_MasBL,
                    REPLACE(REPLACE(k.No_Cont, '-', ''), ' ', '') AS container_no,
                    k.No_Cont AS raw_container_no,
                    k.Size AS size_type,
                    COALESCE(k.Type, 'LCL') AS status,
                    man.blok AS yard_block,
                    m.nopol_in,
                    m.nopol_out,
                    m.No_MasBL AS no_bl,
                    m.Tgl_MasBL AS tgl_bl,
                    H.NO_BC11 AS no_dokumen,
                    H.TGL_BC11 AS tgl_dokumen,
                    H.NO_PLP AS no_plp,
                    H.TGL_PLP AS tgl_plp,
                    CONCAT(m.tgl_datang_cont, ' ', IFNULL(m.jam_datang_cont, '00:00:00')) AS waktu_masuk,
                    CONCAT(man.Tgl_StrippingBC, ' ', IFNULL(man.jamStrippingBC, '00:00:00')) AS waktu_stripping,
                    CONCAT(m.tgl_keluar_cont, ' ', IFNULL(m.jam_keluar_cont, '00:00:00')) AS waktu_keluar
                FROM master_bl m
                INNER JOIN kontainer k ON m.Id_Kontainer_FK = k.Id_Kontainer
                LEFT JOIN manifest man ON man.Id_MasBL_FK = m.Id_MasBL
                LEFT JOIN tpsws_responplp_detail_backup D ON D.NO_BL_AWB = man.No_BL
                LEFT JOIN tpsws_responplp_header_backup H ON H.NO_SURAT = D.NO_SURAT_FK AND H.NO_PLP = D.NO_PLP_FK
                WHERE REPLACE(REPLACE(k.No_Cont, '-', ''), ' ', '') = :noCont
                ORDER BY m.Id_MasBL DESC
                LIMIT 1
            ";
            $stmtG = $pdo_primamas->prepare($sqlGudang);
            $stmtG->execute([':noCont' => $noContClean]);
            $gRow = $stmtG->fetch(PDO::FETCH_ASSOC);

            if (!$gRow) {
                return ['success' => false, 'message' => "Kontainer {$noContClean} tidak ditemukan di database Gudang."];
            }

            $sz = (strpos($gRow['size_type'], '20') !== false) ? '20' : ((strpos($gRow['size_type'], '45') !== false) ? '45' : '40');
            $wMasuk = $formatDmyHis($gRow['waktu_masuk']);
            $wStripping = $formatDmyHis($gRow['waktu_stripping']);
            $wKeluar = $formatDmyHis($gRow['waktu_keluar']);
            $noBl = $gRow['no_bl'] ?: '';
            $tglBl = $formatDmy($gRow['tgl_bl']);

            $timelineG = [];

            // 1. Gate In Gudang LCL
            $dokInGudang = $gRow['no_plp'] ? '3' : '704';
            $noDokInGudang = $gRow['no_plp'] ?: $noBl;
            $tglDokInGudang = $gRow['no_plp'] ? $formatDmy($gRow['tgl_plp']) : $tglBl;

            $pIn = [
                'departemen'      => 'GUDANG',
                'nomorKontainer'  => $noContClean,
                'ukuranKontainer' => $sz,
                'jenisKontainer'  => '7', // 7 = LCL
                'kodeTps'         => env('TPS_KODE', 'PSU0'),
                'kodeGudang'      => env('GUDANG_KODE', 'GPSU'),
                'kodeKegiatan'    => 5,
                'waktuKegiatan'   => $wMasuk ?: date('d-m-Y H:i:s')
            ];
            if ($gRow['nopol_in']) $pIn['nomorPolisi'] = $gRow['nopol_in'];
            if ($noDokInGudang) {
                $pIn['kodeDokumen'] = $dokInGudang;
                $pIn['nomorDokumen'] = $noDokInGudang;
                if ($tglDokInGudang) $pIn['tanggalDokumen'] = $tglDokInGudang;
            }
            if ($noBl) {
                $pIn['nomorBlAwb'] = $noBl;
                if ($tglBl) $pIn['tanggalBlAwb'] = $tglBl;
            }
            $timelineG[] = [
                'step'          => 1,
                'kodeKegiatan'  => 5,
                'kegiatanLabel' => 'Gate In LCL Gudang (In Trailer)',
                'icon'          => '🚚',
                'badgeCategory' => 'Masuk Gudang',
                'available'     => !empty($wMasuk),
                'waktuKegiatan' => $wMasuk ?: '-',
                'nomorPolisi'   => $gRow['nopol_in'] ?: '',
                'nopolLabel'    => $gRow['nopol_in'] ? "In Trailer: {$gRow['nopol_in']}" : '-',
                'kodeDokumen'   => $dokInGudang,
                'namaDokumen'   => ($dokInGudang === '3' ? 'PLP - Dok. PLP/OB (A11)' : 'Master B/L'),
                'dokumenLabel'  => $gRow['no_plp'] ? "PLP (Kode 3): {$gRow['no_plp']}" : ($noBl ? "Master B/L: {$noBl}" : '-'),
                'lokasiYard'    => '-',
                'deskripsi'     => "Kontainer LCL masuk ke gudang untuk stripping muatan",
                'is_sent'       => isset($sentKegiatan[5]),
                'sent_info'     => $sentKegiatan[5] ?? null,
                'payload'       => !empty($wMasuk) ? $pIn : null
            ];

            // 2. Stripping Stuffing LCL Lini 2
            $pStr = [
                'departemen'      => 'GUDANG',
                'nomorKontainer'  => $noContClean,
                'ukuranKontainer' => $sz,
                'jenisKontainer'  => '7',
                'kodeTps'         => env('TPS_KODE', 'PSU0'),
                'kodeGudang'      => env('GUDANG_KODE', 'GPSU'),
                'kodeKegiatan'    => 23,
                'waktuKegiatan'   => $wStripping ?: ($wMasuk ?: date('d-m-Y H:i:s'))
            ];
            if ($noDokInGudang) {
                $pStr['kodeDokumen'] = $dokInGudang;
                $pStr['nomorDokumen'] = $noDokInGudang;
                if ($tglDokInGudang) $pStr['tanggalDokumen'] = $tglDokInGudang;
            }
            if ($noBl) {
                $pStr['nomorBlAwb'] = $noBl;
                if ($tglBl) $pStr['tanggalBlAwb'] = $tglBl;
            }
            $timelineG[] = [
                'step'          => 2,
                'kodeKegiatan'  => 23,
                'kegiatanLabel' => 'Stripping Stuffing LCL Lini 2',
                'icon'          => '📦',
                'badgeCategory' => 'Bongkar LCL',
                'available'     => !empty($wStripping),
                'waktuKegiatan' => $wStripping ?: '-',
                'nomorPolisi'   => '',
                'nopolLabel'    => '-',
                'kodeDokumen'   => $dokInGudang,
                'namaDokumen'   => ($dokInGudang === '3' ? 'PLP - Dok. PLP/OB (A11)' : 'Master B/L'),
                'dokumenLabel'  => $gRow['no_plp'] ? "PLP (Kode 3): {$gRow['no_plp']}" : ($noBl ? "Master B/L: {$noBl}" : '-'),
                'lokasiYard'    => '-',
                'deskripsi'     => "Pembongkaran kargo LCL di dalam gudang",
                'is_sent'       => isset($sentKegiatan[23]),
                'sent_info'     => $sentKegiatan[23] ?? null,
                'payload'       => !empty($wStripping) ? $pStr : null
            ];

            $idMasBL = (int)$gRow['Id_MasBL'];

            // 3. Behandle LCL Lini 2 (Kode 21 - Pemeriksaan Fisik Kargo LCL)
            // Query manifest yang diperiksa fisik (behandle != 0)
            $sqlBehandleG = "
                SELECT man.Id_OB, man.No_BL, man.behandle, man.tglBehandle, man.tglPeriksaCont
                FROM manifest man
                WHERE man.Id_MasBL_FK = :idMasBL AND man.behandle != 0
                ORDER BY man.tglPeriksaCont DESC, man.tglBehandle DESC
                LIMIT 1
            ";
            $stmtBehandleG = $pdo_primamas->prepare($sqlBehandleG);
            $stmtBehandleG->execute([':idMasBL' => $idMasBL]);
            $bRow = $stmtBehandleG->fetch(PDO::FETCH_ASSOC);

            $hasBehandleG = false;
            $wBehandleG = '';
            $pBehandle = null;
            $noBlBehandle = $bRow ? ($bRow['No_BL'] ?: $noBl) : $noBl;

            if ($bRow && (!empty($bRow['tglPeriksaCont']) || !empty($bRow['tglBehandle']))) {
                $wBehandleG = $formatDmyHis($bRow['tglPeriksaCont'] ?: $bRow['tglBehandle']);
                $hasBehandleG = !empty($wBehandleG);
            }

            if ($hasBehandleG) {
                $pBehandle = [
                    'departemen'      => 'GUDANG',
                    'nomorKontainer'  => $noContClean,
                    'ukuranKontainer' => $sz,
                    'jenisKontainer'  => '7', // 7 = LCL
                    'kodeTps'         => env('TPS_KODE', 'PSU0'),
                    'kodeGudang'      => env('GUDANG_KODE', 'GPSU'),
                    'kodeKegiatan'    => 21,
                    'waktuKegiatan'   => $wBehandleG
                ];
                // Referensi PDF: Kode 8 = PPB - Dok. Periksa Fisik
                $pBehandle['kodeDokumen'] = '8';
                $pBehandle['nomorDokumen'] = 'PPB/' . ($noBlBehandle ?: $noContClean);
                if ($tglBl) $pBehandle['tanggalDokumen'] = $tglBl;
                if ($noBlBehandle) {
                    $pBehandle['nomorBlAwb'] = $noBlBehandle;
                    if ($tglBl) $pBehandle['tanggalBlAwb'] = $tglBl;
                }
            }

            $timelineG[] = [
                'step'          => 3,
                'kodeKegiatan'  => 21,
                'kegiatanLabel' => 'Behandle LCL Lini 2 (Pemeriksaan Fisik)',
                'icon'          => '🔍',
                'badgeCategory' => 'Pemeriksaan Pabean',
                'available'     => $hasBehandleG,
                'waktuKegiatan' => $wBehandleG ?: '-',
                'nomorPolisi'   => '',
                'nopolLabel'    => '-',
                'kodeDokumen'   => '8',
                'namaDokumen'   => 'PPB - Dok. Periksa Fisik',
                'dokumenLabel'  => $hasBehandleG ? "PPB (Kode 8) [B/L: {$noBlBehandle}]" : '-',
                'lokasiYard'    => '-',
                'deskripsi'     => $hasBehandleG ? "Pemeriksaan fisik pabean kargo LCL di gudang (House B/L: {$noBlBehandle})" : "Pemeriksaan fisik belum dilaksanakan / tidak ada behandle pada kontainer ini",
                'is_sent'       => isset($sentKegiatan[21]) || ($noBlBehandle && isset($sentKegiatan["21_{$noBlBehandle}"])),
                'sent_info'     => $sentKegiatan["21_{$noBlBehandle}"] ?? ($sentKegiatan[21] ?? null),
                'payload'       => $pBehandle
            ];

            // 4. Gate Out Gudang (Empty Kontainer Keluar ex Stripping) (Kode 6)
            // Referensi PDF: Kode 40 = Pengeluaran Empty Container ex. Stripping
            $pOut = [
                'departemen'      => 'GUDANG',
                'nomorKontainer'  => $noContClean,
                'ukuranKontainer' => $sz,
                'jenisKontainer'  => '4', // 4 = EMPTY
                'kodeTps'         => env('TPS_KODE', 'PSU0'),
                'kodeGudang'      => env('GUDANG_KODE', 'GPSU'),
                'kodeKegiatan'    => 6,
                'waktuKegiatan'   => $wKeluar ?: ($wStripping ?: date('d-m-Y H:i:s'))
            ];
            if ($gRow['nopol_out']) $pOut['nomorPolisi'] = $gRow['nopol_out'];
            $pOut['kodeDokumen'] = '40';
            $pOut['nomorDokumen'] = $noBl ?: ($gRow['no_dokumen'] ?: $noContClean);
            if ($tglBl) $pOut['tanggalDokumen'] = $tglBl;
            if ($noBl) {
                $pOut['nomorBlAwb'] = $noBl;
                if ($tglBl) $pOut['tanggalBlAwb'] = $tglBl;
            }

            $timelineG[] = [
                'step'          => 4,
                'kodeKegiatan'  => 6,
                'kegiatanLabel' => 'Gate Out Empty Gudang (Out Trailer)',
                'icon'          => '🚚',
                'badgeCategory' => 'Keluar Kosong',
                'available'     => !empty($wKeluar),
                'waktuKegiatan' => $wKeluar ?: '-',
                'nomorPolisi'   => $gRow['nopol_out'] ?: '',
                'nopolLabel'    => $gRow['nopol_out'] ? "Out Trailer: {$gRow['nopol_out']}" : '-',
                'kodeDokumen'   => '40',
                'namaDokumen'   => 'Pengeluaran Empty Container ex. Stripping',
                'dokumenLabel'  => "Empty ex. Stripping [Kode 40]: " . ($noBl ?: ($gRow['no_dokumen'] ?: $noContClean)),
                'lokasiYard'    => '-',
                'deskripsi'     => "Kontainer kosong (Empty) ex stripping keluar meninggalkan gudang/depo",
                'is_sent'       => isset($sentKegiatan[6]),
                'sent_info'     => $sentKegiatan[6] ?? null,
                'payload'       => !empty($wKeluar) ? $pOut : null
            ];

            // 5+. Pengeluaran Barang / Delivery Kargo LCL per House B/L (Kode 20 - PICKUP LINI 2)
            $sqlPickups = "
                SELECT 
                    inv.Id_InvGud, inv.No_SPPB, inv.Tgl_SPPB, inv.JNS_SPPB, inv.Tgl_Keluar,
                    sj.No_surat, sj.Nopol, sj.Tgl_Out,
                    man.Id_OB, man.No_BL
                FROM manifest man
                INNER JOIN invoice_gudang inv ON inv.Id_OB_FK = man.Id_OB
                LEFT JOIN sj ON sj.Id_OB_FK = man.Id_OB
                WHERE man.Id_MasBL_FK = :idMasBL 
                  AND inv.No_SPPB IS NOT NULL AND inv.No_SPPB != ''
                ORDER BY sj.Tgl_Out ASC, inv.Tgl_Keluar ASC, inv.Id_InvGud ASC
            ";
            $stmtPick = $pdo_primamas->prepare($sqlPickups);
            $stmtPick->execute([':idMasBL' => $idMasBL]);
            $pickupRows = $stmtPick->fetchAll(PDO::FETCH_ASSOC);

            if (empty($pickupRows)) {
                // Placeholder alur jika kargo belum diambil
                $timelineG[] = [
                    'step'          => 5,
                    'kodeKegiatan'  => 20,
                    'kegiatanLabel' => 'Pickup Kargo LCL Lini 2 (Pengeluaran Barang)',
                    'icon'          => '📦🚛',
                    'badgeCategory' => 'Pengeluaran Kargo',
                    'available'     => false,
                    'waktuKegiatan' => '-',
                    'nomorPolisi'   => '',
                    'nopolLabel'    => '-',
                    'kodeDokumen'   => '1',
                    'namaDokumen'   => 'SPPB (Surat Persetujuan Pengeluaran Barang)',
                    'dokumenLabel'  => '-',
                    'lokasiYard'    => '-',
                    'deskripsi'     => 'Kargo LCL belum diambil oleh consignee / belum ada data SPPB & Surat Jalan pengeluaran kargo',
                    'is_sent'       => false,
                    'sent_info'     => null,
                    'payload'       => null
                ];
            } else {
                $stepNum = 5;
                foreach ($pickupRows as $pRow) {
                    $houseBl = trim((string)$pRow['No_BL']);
                    $noSppb = trim((string)$pRow['No_SPPB']);
                    $tglSppb = $formatDmy($pRow['Tgl_SPPB']);
                    $nopolPickup = trim((string)$pRow['Nopol']);
                    $noSJ = trim((string)$pRow['No_surat']);
                    
                    // Waktu pengeluaran: Tgl_Out surat jalan ?: Tgl_Keluar invoice
                    $wPickupRaw = $pRow['Tgl_Out'] ?: ($pRow['Tgl_Keluar'] ? $pRow['Tgl_Keluar'] . ' 10:00:00' : '');
                    $wPickup = $formatDmyHis($wPickupRaw);

                    // Pemetaan Dokumen Pabean SPPB
                    $sppbJns = strtoupper(trim((string)$pRow['JNS_SPPB']));
                    $kdDokPick = '1'; // Default: 1 (SPPB BC 2.0)
                    $namaDokPick = 'SPPB BC 2.0';

                    if (strpos($sppbJns, '2.3') !== false) {
                        $kdDokPick = '2';
                        $namaDokPick = 'SPPB BC 2.3';
                    } elseif (strpos($sppbJns, '1.5') !== false) {
                        $kdDokPick = '9';
                        $namaDokPick = 'BCF 1.5 (Barang Tidak Dikuasai)';
                    } elseif (strpos($sppbJns, '1.6') !== false || strpos($sppbJns, 'PLB') !== false) {
                        $kdDokPick = '41';
                        $namaDokPick = 'SPPB PLB - BC 1.6';
                    } elseif (strpos($sppbJns, 'RE-EXP') !== false || strpos($sppbJns, '1.2') !== false) {
                        $kdDokPick = '28';
                        $namaDokPick = 'Dokumen BC 1.2 - Re-Ekspor';
                    } elseif (strpos($sppbJns, 'KEK') !== false) {
                        $kdDokPick = '64';
                        $namaDokPick = 'Pengeluaran KEK';
                    }

                    $pPayload = [
                        'departemen'      => 'GUDANG',
                        'nomorKontainer'  => $noContClean,
                        'ukuranKontainer' => $sz,
                        'jenisKontainer'  => '7', // 7 = LCL
                        'kodeTps'         => env('TPS_KODE', 'PSU0'),
                        'kodeGudang'      => env('GUDANG_KODE', 'GPSU'),
                        'kodeKegiatan'    => 20,
                        'waktuKegiatan'   => $wPickup ?: date('d-m-Y H:i:s')
                    ];
                    if ($nopolPickup) $pPayload['nomorPolisi'] = $nopolPickup;
                    $pPayload['kodeDokumen'] = $kdDokPick;
                    $pPayload['nomorDokumen'] = $noSppb;
                    if ($tglSppb) $pPayload['tanggalDokumen'] = $tglSppb;
                    if ($houseBl) {
                        $pPayload['nomorBlAwb'] = $houseBl;
                        if ($tglBl) $pPayload['tanggalBlAwb'] = $tglBl;
                    }

                    // Cek status pengiriman berdasarkan kode 20 + BL atau nomor dokumen
                    $isSentPick = isset($sentKegiatan["20_{$houseBl}"]) || isset($sentKegiatan["20_{$noSppb}"]);
                    $sentInfoPick = $sentKegiatan["20_{$houseBl}"] ?? ($sentKegiatan["20_{$noSppb}"] ?? null);

                    $timelineG[] = [
                        'step'          => $stepNum,
                        'kodeKegiatan'  => 20,
                        'kegiatanLabel' => "Pickup Kargo LCL Lini 2 (B/L: {$houseBl})",
                        'icon'          => '📦🚛',
                        'badgeCategory' => 'Pengeluaran Kargo',
                        'available'     => !empty($wPickup) && !empty($noSppb),
                        'waktuKegiatan' => $wPickup ?: '-',
                        'nomorPolisi'   => $nopolPickup,
                        'nopolLabel'    => $nopolPickup ? "Armada: {$nopolPickup}" : ($noSJ ? "SJ: {$noSJ}" : '-'),
                        'kodeDokumen'   => $kdDokPick,
                        'namaDokumen'   => $namaDokPick,
                        'dokumenLabel'  => "{$namaDokPick} [Kd {$kdDokPick}]: {$noSppb}",
                        'lokasiYard'    => '-',
                        'deskripsi'     => "Pengeluaran kargo LCL B/L {$houseBl} berdasarkan {$namaDokPick} No. {$noSppb}" . ($noSJ ? " (SJ: {$noSJ})" : ''),
                        'is_sent'       => $isSentPick,
                        'sent_info'     => $sentInfoPick,
                        'payload'       => (!empty($wPickup) && !empty($noSppb)) ? $pPayload : null
                    ];
                    $stepNum++;
                }
            }

            // Urutkan riwayat alur operasional Gudang LCL secara KRONOLOGIS berdasarkan waktuKegiatan sesungguhnya
            $availStepsG = [];
            $unavailStepsG = [];
            foreach ($timelineG as $st) {
                if ($st['available'] && !empty($st['waktuKegiatan']) && $st['waktuKegiatan'] !== '-') {
                    $availStepsG[] = $st;
                } else {
                    $unavailStepsG[] = $st;
                }
            }

            $priorityOrderG = [
                5  => 1, // Gate In LCL
                23 => 2, // Stripping
                6  => 3, // Gate Out Empty
                21 => 4, // Behandle
                19 => 5, // Truck In
                20 => 6  // Pickup
            ];

            usort($availStepsG, function($a, $b) use ($priorityOrderG) {
                $timeA = 0;
                $timeB = 0;
                if (!empty($a['waktuKegiatan']) && $a['waktuKegiatan'] !== '-') {
                    $dA = DateTime::createFromFormat('d-m-Y H:i:s', $a['waktuKegiatan']);
                    $timeA = $dA ? $dA->getTimestamp() : strtotime($a['waktuKegiatan']);
                }
                if (!empty($b['waktuKegiatan']) && $b['waktuKegiatan'] !== '-') {
                    $dB = DateTime::createFromFormat('d-m-Y H:i:s', $b['waktuKegiatan']);
                    $timeB = $dB ? $dB->getTimestamp() : strtotime($b['waktuKegiatan']);
                }

                if ($timeA !== $timeB) {
                    return $timeA <=> $timeB;
                }

                $pA = $priorityOrderG[$a['kodeKegiatan']] ?? 99;
                $pB = $priorityOrderG[$b['kodeKegiatan']] ?? 99;
                return $pA <=> $pB;
            });

            $sortedTimelineG = [];
            $stepNum = 1;
            foreach ($availStepsG as $st) {
                $st['step'] = $stepNum++;
                $sortedTimelineG[] = $st;
            }
            foreach ($unavailStepsG as $st) {
                $st['step'] = $stepNum++;
                $sortedTimelineG[] = $st;
            }
            $timelineG = $sortedTimelineG;

            return [
                'success'    => true,
                'departemen' => 'GUDANG',
                'container'  => [
                    'nomorKontainer'  => $noContClean,
                    'ukuranKontainer' => $sz,
                    'jenisKontainer'  => '7',
                    'statusKontainer' => 'LCL',
                    'lokasiYard'      => $gRow['yard_block'] ?: '-',
                    'inTrailer'       => $gRow['nopol_in'] ?: '-',
                    'outTrailer'      => $gRow['nopol_out'] ?: '-',
                    'noBl'            => $noBl ?: '-'
                ],
                'timeline'   => $timelineG
            ];
        }

    } catch (Exception $e) {
        error_log("Error get_container_timeline: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Gagal menelusuri alur kontainer: ' . $e->getMessage()
        ];
    }
    }
}
