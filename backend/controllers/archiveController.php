<?php

require_once __DIR__ . '/../models/archives.php';

function getArchiveDataset() {
    $archives = new Archives();

    echo json_encode([
        "status" => "success",
        "data" => $archives->getDataset()
    ]);
}

function restoreArchivedRecord() {
    $input = json_decode(file_get_contents("php://input"), true);
    $type = $input['type'] ?? '';
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    $authUser = getCurrentAuthUser();
    $userID = isset($authUser->sub) ? (int) $authUser->sub : 0;

    if ($type === '' || $id <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Archive type and record ID are required"]);
        return;
    }

    $archives = new Archives();

    try {
        if ($archives->restore($type, $id, $userID)) {
            echo json_encode(["status" => "success", "message" => "Archived record restored successfully"]);
            return;
        }

        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Archived record was not found or was already restored"]);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
