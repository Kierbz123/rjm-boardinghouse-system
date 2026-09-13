<?php

namespace App\Controllers;

use App\Models\OccupancySnapshot;

class OccupancyController
{
    public static function trend(): void
    {
        OccupancySnapshot::takeToday();
        $trend = OccupancySnapshot::trend(30);
        require __DIR__ . '/../Views/admin/occupancy.php';
    }
}
