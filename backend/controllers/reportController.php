<?php

require_once __DIR__ . '../../models/reports.php';

function getReportDataset() {
    $reports = new Reports();
    $dateFrom = validateReportDate($_GET['dateFrom'] ?? '');
    $dateUntil = validateReportDate($_GET['dateUntil'] ?? '');

    if ($dateFrom !== '' && $dateUntil !== '' && $dateFrom > $dateUntil) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Date range is invalid"]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "data" => $reports->getDataset($dateFrom, $dateUntil)
    ]);
}

function validateReportDate($value) {
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Report date is invalid"]);
        exit;
    }

    return $value;
}

function validateReportText($value, $fieldName, $maxLength) {
    $value = trim((string) $value);

    if ($value === '' || strlen($value) > $maxLength || preg_match('/[<>{}]/', $value)) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => $fieldName . " is invalid"]);
        exit;
    }

    return $value;
}

function logReportExport() {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
        exit;
    }

    $reportName = validateReportText($input['reportName'] ?? '', 'Report name', 100);
    $reportTypes = validateReportText($input['reportTypes'] ?? '', 'Report types', 1000);
    $exportFormat = strtolower(validateReportText($input['exportFormat'] ?? '', 'Export format', 20));
    $dateFrom = validateReportDate($input['dateFrom'] ?? '');
    $dateUntil = validateReportDate($input['dateUntil'] ?? '');

    if ($dateFrom !== '' && $dateUntil !== '' && $dateFrom > $dateUntil) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Date range is invalid"]);
        exit;
    }

    if (!in_array($exportFormat, ['pdf', 'excel'], true)) {
        http_response_code(422);
        echo json_encode(["status" => "error", "message" => "Export format is invalid"]);
        exit;
    }

    $authUser = getCurrentAuthUser();
    $userID = isset($authUser->sub) ? (int) $authUser->sub : 0;

    $reports = new Reports();
    $reports->logExport($userID, $reportName, $reportTypes, $exportFormat, $dateFrom, $dateUntil);

    echo json_encode([
        "status" => "success",
        "message" => "Report export recorded"
    ]);
}
