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

function getProcessDetails() {
    $processID = validateProcessID($_GET['processID'] ?? null);
    $process = new Process();
    $data = $process->getDetails($processID);

    if ($data === null) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Process was not found"]);
        return;
    }

    echo json_encode([
        "status" => "success",
        "data" => $data
    ]);
}

function failProcessValidation($message) {
    http_response_code(422);
    echo json_encode([
        "status" => "error",
        "message" => $message
    ]);
    exit;
}

function validateProcessText($value, $fieldName, $maxLength = 255) {
    $value = trim((string) $value);

    if ($value === '' || strlen($value) > $maxLength) {
        failProcessValidation($fieldName . " is invalid");
    }

    if (preg_match('/[<>{}]/', $value)) {
        failProcessValidation($fieldName . " contains invalid characters");
    }

    return $value;
}

function validateProcessName($value) {
    $value = validateProcessText($value, 'Process name', 50);

    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9\s().,&\/-]*$/', $value)) {
        failProcessValidation("Process name contains invalid characters");
    }

    return $value;
}

function validateProcessDescription($value) {
    $value = trim((string) $value);

    if ($value === '') {
        return 'No description';
    }

    return validateProcessText($value, 'Process description', 255);
}

function validateProcessID($value) {
    if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value <= 0) {
        failProcessValidation("Process ID must be a positive whole number");
    }

    return (int) $value;
}

function createProcess() {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    $input['processName'] = validateProcessName($input['processName'] ?? '');
    $input['processDescription'] = validateProcessDescription($input['processDescription'] ?? '');

    $process = new Process();

    if ($process->processExistsIgnoreCase($input['processName'])) {
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "Process already exists, including archived records"]);
        exit;
    }

    if ($process->createProcess($input)) {
        http_response_code(201);
        echo json_encode(["status" => "success", "message" => "Process registered successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to register process"]);
    }
}

function updateProcess() {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    $input['processID'] = validateProcessID($input['processID'] ?? null);
    $input['processName'] = validateProcessName($input['processName'] ?? '');
    $input['processDescription'] = validateProcessDescription($input['processDescription'] ?? '');

    $process = new Process();

    if ($process->processNameExistsForOtherProcess($input['processName'], $input['processID'])) {
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "Process already exists, including archived records"]);
        exit;
    }

    if ($process->updateProcess($input)) {
        echo json_encode(["status" => "success", "message" => "Process updated successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to update process"]);
    }
}

function archiveProcess() {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    $processID = validateProcessID($input['processID'] ?? null);
    $process = new Process();

    if ($process->archiveProcess($processID)) {
        echo json_encode(["status" => "success", "message" => "Process archived successfully"]);
    } else {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Process was not found or is already archived"]);
    }
}
