<?php

require_once __DIR__ . '../../models/process.php';

function getProcesses() {
    $process = new Process();
    $data = $process->getAll();

    header('Content-Type: application/json');
    
    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);
}