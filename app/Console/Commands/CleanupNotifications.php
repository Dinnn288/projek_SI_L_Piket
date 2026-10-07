<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CleanupNotifications extends Command
{
    protected $signature = 'notifications:cleanup {--days=30 : Hapus notifikasi yang lebih tua dari N hari}';

    protected $description = 'Hapus notifikasi yang berumur lebih dari 30 hari (retensi notifikasi SI-LPiket).';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = Carbon::now()->subDays($days);

        // Hapus massal via query agar tidak memuat ribuan record ke memori.
        // Aman diulang: hanya menyentuh created_at < cutoff.
        $deleted = Notification::where('created_at', '<', $cutoff)->delete();

        $this->info("Cleanup selesai: {$deleted} notifikasi lebih tua dari {$days} hari dihapus.");

        return self::SUCCESS;
    }
}
