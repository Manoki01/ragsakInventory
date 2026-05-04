<?php

require_once __DIR__ . '/../config/database.php';

class Reports {
    private $conn;

    public function __construct() {
        $this->conn = Database::connect();
    }

    private function escape($value) {
        return $this->conn->real_escape_string((string) $value);
    }

    private function dateFilter($column, $dateFrom, $dateUntil) {
        $conditions = [];

        if ($dateFrom !== '') {
            $conditions[] = "DATE({$column}) >= '" . $this->escape($dateFrom) . "'";
        }

        if ($dateUntil !== '') {
            $conditions[] = "DATE({$column}) <= '" . $this->escape($dateUntil) . "'";
        }

        return $conditions ? " AND " . implode(" AND ", $conditions) : "";
    }

    private function fetchAll($query) {
        $result = $this->conn->query($query);

        if (!$result) {
            throw new Exception($this->conn->error);
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getDataset($dateFrom = '', $dateUntil = '') {
        return [
            'generatedAt' => date('Y-m-d H:i:s'),
            'dateFrom' => $dateFrom,
            'dateUntil' => $dateUntil,
            'inventory' => $this->getInventory(),
            'stockMovements' => $this->getStockMovements($dateFrom, $dateUntil),
            'orders' => $this->getOrders(false, $dateFrom, $dateUntil),
            'archivedOrders' => $this->getOrders(true, $dateFrom, $dateUntil),
            'archivedRecords' => $this->getArchivedRecords($dateFrom, $dateUntil),
            'userActivity' => $this->getUserActivity($dateFrom, $dateUntil),
            'productConsumption' => $this->getProductConsumption(),
            'salesDemand' => $this->getSalesDemand($dateFrom, $dateUntil),
            'reportTransactions' => $this->getReportTransactions($dateFrom, $dateUntil)
        ];
    }

    private function getInventory() {
        return [
            'products' => $this->fetchAll("
                SELECT
                    p.productName,
                    p.unitType,
                    p.productPrice,
                    pr.processName,
                    pf.flowOrder,
                    ps.quantity
                FROM tbl_products p
                INNER JOIN tbl_processFlow pf ON p.productID = pf.productID
                INNER JOIN tbl_process pr ON pf.processID = pr.processID
                INNER JOIN tbl_productStock ps ON pf.flowID = ps.flowID
                WHERE p.deleted_at IS NULL
                AND pr.deleted_at IS NULL
                ORDER BY pf.flowOrder, pr.processName, p.productName
            "),
            'rawMaterials' => $this->fetchAll("
                SELECT rawMaterialName, quantity, unitType, rawMaterialPrice, matType
                FROM tbl_rawMaterials
                WHERE deleted_at IS NULL
                ORDER BY rawMaterialName
            "),
            'packaging' => $this->fetchAll("
                SELECT packagingName, quantity, unitType, packagingPrice, packagingType
                FROM tbl_packaging
                WHERE deleted_at IS NULL
                ORDER BY packagingName
            ")
        ];
    }

    private function getStockMovements($dateFrom, $dateUntil) {
        $productDate = $this->dateFilter('pc.date', $dateFrom, $dateUntil);
        $rawDate = $this->dateFilter('rc.date', $dateFrom, $dateUntil);
        $packagingDate = $this->dateFilter('pcg.date', $dateFrom, $dateUntil);

        return $this->fetchAll("
            SELECT 'Product' AS itemType, p.productName AS itemName, pr.processName AS groupName,
                pc.action, pc.quantity, pc.initialQuantity, pc.finalQuantity, pc.reason, pc.date
            FROM tbl_prodChangelogs pc
            INNER JOIN tbl_processFlow pf ON pc.flowID = pf.flowID
            INNER JOIN tbl_products p ON pf.productID = p.productID
            INNER JOIN tbl_process pr ON pf.processID = pr.processID
            WHERE 1=1 {$productDate}
            UNION ALL
            SELECT 'Raw Material' AS itemType, rm.rawMaterialName AS itemName, rm.matType AS groupName,
                rc.action, rc.quantity, rc.initialQuantity, rc.finalQuantity, rc.reason, rc.date
            FROM tbl_rawMatChangelogs rc
            INNER JOIN tbl_rawMaterials rm ON rc.rawMaterialID = rm.rawMaterialID
            WHERE 1=1 {$rawDate}
            UNION ALL
            SELECT 'Packaging' AS itemType, pk.packagingName AS itemName, pk.packagingType AS groupName,
                pcg.action, pcg.quantity, pcg.initialQuantity, pcg.finalQuantity, pcg.reason, pcg.date
            FROM tbl_packagingChangelogs pcg
            INNER JOIN tbl_packaging pk ON pcg.packagingID = pk.packagingID
            WHERE 1=1 {$packagingDate}
            ORDER BY date DESC
        ");
    }

    private function getOrders($archived, $dateFrom, $dateUntil) {
        $deletedFilter = $archived ? "IS NOT NULL" : "IS NULL";
        $dateColumn = $archived ? 'o.deleted_at' : 'o.created_at';
        $dateFilter = $this->dateFilter($dateColumn, $dateFrom, $dateUntil);

        return $this->fetchAll("
            SELECT
                o.orderID,
                o.customerName,
                p.productName,
                p.productPrice,
                o.quantity,
                o.orderStatus,
                DATE(o.dateCompletion) AS dateCompletion,
                o.created_at,
                o.deleted_at,
                (o.quantity * p.productPrice) AS totalRevenue
            FROM tbl_orders o
            INNER JOIN tbl_products p ON o.productID = p.productID
            WHERE o.deleted_at {$deletedFilter}
            {$dateFilter}
            ORDER BY o.orderID ASC
        ");
    }

    private function getArchivedRecords($dateFrom, $dateUntil) {
        $productDate = $this->dateFilter('deleted_at', $dateFrom, $dateUntil);
        $rawDate = $this->dateFilter('deleted_at', $dateFrom, $dateUntil);
        $packagingDate = $this->dateFilter('deleted_at', $dateFrom, $dateUntil);
        $processDate = $this->dateFilter('deleted_at', $dateFrom, $dateUntil);
        $orderDate = $this->dateFilter('o.deleted_at', $dateFrom, $dateUntil);

        return [
            'products' => $this->fetchAll("
                SELECT productName AS name, deleted_at
                FROM tbl_products
                WHERE deleted_at IS NOT NULL
                {$productDate}
                ORDER BY deleted_at DESC
            "),
            'rawMaterials' => $this->fetchAll("
                SELECT rawMaterialName AS name, deleted_at
                FROM tbl_rawMaterials
                WHERE deleted_at IS NOT NULL
                {$rawDate}
                ORDER BY deleted_at DESC
            "),
            'packaging' => $this->fetchAll("
                SELECT packagingName AS name, deleted_at
                FROM tbl_packaging
                WHERE deleted_at IS NOT NULL
                {$packagingDate}
                ORDER BY deleted_at DESC
            "),
            'processes' => $this->fetchAll("
                SELECT processName AS name, deleted_at
                FROM tbl_process
                WHERE deleted_at IS NOT NULL
                {$processDate}
                ORDER BY deleted_at DESC
            "),
            'orders' => $this->fetchAll("
                SELECT CONCAT('Order for ', p.productName, ' - ', o.customerName) AS name, o.deleted_at
                FROM tbl_orders o
                INNER JOIN tbl_products p ON o.productID = p.productID
                WHERE o.deleted_at IS NOT NULL
                {$orderDate}
                ORDER BY o.deleted_at DESC
            ")
        ];
    }

    private function getUserActivity($dateFrom, $dateUntil) {
        $dateFilter = $this->dateFilter('ot.date', $dateFrom, $dateUntil);

        return $this->fetchAll("
            SELECT
                COALESCE(u.username, CONCAT('User #', ot.userID)) AS username,
                ot.orderID,
                ot.action,
                ot.date
            FROM tbl_orderTransactions ot
            LEFT JOIN tbl_user u ON ot.userID = u.userID
            WHERE 1=1 {$dateFilter}
            ORDER BY username, ot.date DESC
        ");
    }

    private function getProductConsumption() {
        return $this->fetchAll("
            SELECT
                p.productName,
                pr.processName,
                'Raw Material' AS itemType,
                rm.rawMaterialName AS itemName,
                brm.quantityRequired,
                rm.unitType
            FROM tbl_bom b
            INNER JOIN tbl_processFlow pf ON b.flowID = pf.flowID
            INNER JOIN tbl_products p ON pf.productID = p.productID
            INNER JOIN tbl_process pr ON pf.processID = pr.processID
            INNER JOIN tbl_bomRawMaterials brm ON b.bomID = brm.bomID
            INNER JOIN tbl_rawMaterials rm ON brm.rawMaterialID = rm.rawMaterialID
            WHERE p.deleted_at IS NULL
            UNION ALL
            SELECT
                p.productName,
                pr.processName,
                'Packaging' AS itemType,
                pk.packagingName AS itemName,
                bp.quantityRequired,
                pk.unitType
            FROM tbl_bom b
            INNER JOIN tbl_processFlow pf ON b.flowID = pf.flowID
            INNER JOIN tbl_products p ON pf.productID = p.productID
            INNER JOIN tbl_process pr ON pf.processID = pr.processID
            INNER JOIN tbl_bomPackaging bp ON b.bomID = bp.bomID
            INNER JOIN tbl_packaging pk ON bp.packagingID = pk.packagingID
            WHERE p.deleted_at IS NULL
            ORDER BY productName, processName, itemType, itemName
        ");
    }

    private function getSalesDemand($dateFrom, $dateUntil) {
        $dateFilter = $this->dateFilter('o.created_at', $dateFrom, $dateUntil);

        return $this->fetchAll("
            SELECT
                p.productName,
                p.productPrice,
                COUNT(o.orderID) AS totalOrders,
                SUM(o.quantity) AS totalQtyOrdered,
                SUM(CASE WHEN o.orderStatus = 'completed' THEN o.quantity ELSE 0 END) AS completedQty,
                SUM(CASE WHEN o.orderStatus = 'late' THEN o.quantity ELSE 0 END) AS lateQty,
                SUM(CASE WHEN o.orderStatus = 'pending' THEN o.quantity ELSE 0 END) AS pendingQty,
                ((SUM(CASE WHEN o.orderStatus IN ('completed', 'late') THEN o.quantity ELSE 0 END)) * p.productPrice) AS totalRevenue,
                ((SUM(CASE WHEN o.orderStatus = 'pending' THEN o.quantity ELSE 0 END)) * p.productPrice) AS pendingRevenue,
                ((SUM(CASE WHEN o.orderStatus IN ('completed', 'late', 'pending') THEN o.quantity ELSE 0 END)) * p.productPrice) AS expectedRevenue
            FROM tbl_orders o
            INNER JOIN tbl_products p ON o.productID = p.productID
            WHERE o.deleted_at IS NULL
            {$dateFilter}
            GROUP BY p.productID, p.productName, p.productPrice
            ORDER BY totalQtyOrdered DESC
        ");
    }

    public function logExport($userID, $reportName, $reportTypes, $exportFormat, $dateFrom, $dateUntil) {
        $dateFromValue = $dateFrom === '' ? null : $dateFrom;
        $dateUntilValue = $dateUntil === '' ? null : $dateUntil;

        $stmt = $this->conn->prepare("
            INSERT INTO tbl_reportTransactions (
                userID,
                reportName,
                reportTypes,
                exportFormat,
                dateFrom,
                dateUntil
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "isssss",
            $userID,
            $reportName,
            $reportTypes,
            $exportFormat,
            $dateFromValue,
            $dateUntilValue
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to log report export");
        }

        return true;
    }

    private function getReportTransactions($dateFrom, $dateUntil) {
        $dateFilter = $this->dateFilter('rt.date', $dateFrom, $dateUntil);

        return $this->fetchAll("
            SELECT
                COALESCE(u.username, CONCAT('User #', rt.userID)) AS username,
                rt.reportName,
                rt.reportTypes,
                rt.exportFormat,
                rt.dateFrom,
                rt.dateUntil,
                rt.date
            FROM tbl_reportTransactions rt
            LEFT JOIN tbl_user u ON rt.userID = u.userID
            WHERE 1=1 {$dateFilter}
            ORDER BY rt.date DESC
        ");
    }
}
