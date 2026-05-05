<?php

require_once __DIR__ . '/../models/dashboard.php';

function getDashboardDataset() {
    $dashboard = new Dashboard();

    echo json_encode([
        "status" => "success",
        "data" => $dashboard->getDataset()
    ]);
}
