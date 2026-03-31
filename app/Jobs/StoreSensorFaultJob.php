<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StoreSensorFaultJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $sensors;

    public function __construct($sensors)
    {
        $this->sensors = $sensors;
    }

    public function handle(): void
{
    $insertData = [];

    foreach ($this->sensors as $sensor) {

        $sensorId = $sensor['sensor_id'];
        $value = $sensor['value'];
        $alarmTime = $sensor['alarm_time'];

        // 🔥 convert time
        $formattedTime = Carbon::parse($alarmTime)
            ->timezone('Asia/Dhaka')
            ->format('Y-m-d H:i:s');

        // 🔥 last value check
        $lastRecord = DB::table('sensor_fault_tables')
            ->where('sensor_id', $sensorId)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastRecord && $lastRecord->value == $value) {
            continue;
        }

        $insertData[] = [
            'sensor_id' => $sensorId,
            'value' => $value,
            'created_at' => $formattedTime,
            'updated_at' => $formattedTime,
        ];
    }

    if (!empty($insertData)) {
        DB::table('sensor_fault_tables')->insert($insertData);
    }
}
}
