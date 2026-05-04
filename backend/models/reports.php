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
            'productConsumption' => $this->getProductConsumption($dateFrom, $dateUntil),
            'salesDemand' => $this->getSalesDemand($dateFrom, $dateUntil),
            'salesDemandSummary' => $this->getSalesDemandSummary($dateFrom, $dateUntil),
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
        $productDate = $this->dateFilter('pt.date', $dateFrom, $dateUntil);
        $rawDate = $this->dateFilter('rt.date', $dateFrom, $dateUntil);
        $packagingDate = $this->dateFilter('pkt.date', $dateFrom, $dateUntil);
        $orderDate = $this->dateFilter('ot.date', $dateFrom, $dateUntil);
        $processDate = $this->dateFilter('pr.deleted_at', $dateFrom, $dateUntil);

        return $this->fetchAll("
            SELECT
                COALESCE(u.username, CONCAT('User #', pt.userID)) AS username,
                'Products' AS moduleType,
                pr.processName AS groupName,
                p.productName AS recordName,
                NULL AS orderID,
                CONCAT(pt.action, ' - ', pt.quantity, ' ', p.unitType) AS action,
                pt.status,
                pt.date
            FROM tbl_prodTransactions pt
            INNER JOIN tbl_processFlow pf ON pt.flowID = pf.flowID
            INNER JOIN tbl_products p ON pf.productID = p.productID
            INNER JOIN tbl_process pr ON pf.processID = pr.processID
            LEFT JOIN tbl_user u ON pt.userID = u.userID
            WHERE 1=1 {$productDate}
            UNION ALL
            SELECT
                COALESCE(u.username, CONCAT('User #', rt.userID)) AS username,
                'Raw Materials' AS moduleType,
                rm.matType AS groupName,
                rm.rawMaterialName AS recordName,
                NULL AS orderID,
                CONCAT(rt.action, ' - ', rt.quantity, ' ', rm.unitType) AS action,
                rt.status,
                rt.date
            FROM tbl_rawMatTransactions rt
            INNER JOIN tbl_rawMaterials rm ON rt.rawMaterialID = rm.rawMaterialID
            LEFT JOIN tbl_user u ON rt.userID = u.userID
            WHERE 1=1 {$rawDate}
            UNION ALL
            SELECT
                COALESCE(u.username, CONCAT('User #', pkt.userID)) AS username,
                'Packaging' AS moduleType,
                pk.packagingType AS groupName,
                pk.packagingName AS recordName,
                NULL AS orderID,
                CONCAT(pkt.action, ' - ', pkt.quantity, ' ', pk.unitType) AS action,
                pkt.status,
                pkt.date
            FROM tbl_packagingTransactions pkt
            INNER JOIN tbl_packaging pk ON pkt.packagingID = pk.packagingID
            LEFT JOIN tbl_user u ON pkt.userID = u.userID
            WHERE 1=1 {$packagingDate}
            UNION ALL
            SELECT
                COALESCE(u.username, CONCAT('User #', ot.userID)) AS username,
                'Orders' AS moduleType,
                o.orderStatus AS groupName,
                CONCAT('Order #', ot.orderID, ' - ', p.productName) AS recordName,
                ot.orderID,
                ot.action,
                o.orderStatus AS status,
                ot.date
            FROM tbl_orderTransactions ot
            LEFT JOIN tbl_orders o ON ot.orderID = o.orderID
            LEFT JOIN tbl_products p ON o.productID = p.productID
            LEFT JOIN tbl_user u ON ot.userID = u.userID
            WHERE 1=1 {$orderDate}
            UNION ALL
            SELECT
                'System' AS username,
                'Processes' AS moduleType,
                'Archived Processes' AS groupName,
                pr.processName AS recordName,
                NULL AS orderID,
                'Archived process' AS action,
                'archived' AS status,
                pr.deleted_at AS date
            FROM tbl_process pr
            WHERE pr.deleted_at IS NOT NULL {$processDate}
            ORDER BY moduleType, username, date DESC
        ");
    }

    private function getProductConsumption($dateFrom, $dateUntil) {
        $rawDate = $this->dateFilter('rc.date', $dateFrom, $dateUntil);
        $packagingDate = $this->dateFilter('pc.date', $dateFrom, $dateUntil);

        return $this->fetchAll("
            SELECT
                'Product Stock In' AS consumptionGroup,
                'Raw Material' AS itemType,
                rm.rawMaterialName AS itemName,
                rc.action,
                rc.quantity AS quantityConsumed,
                rm.rawMaterialPrice AS unitPrice,
                (rc.quantity * rm.rawMaterialPrice) AS assetValue,
                rm.unitType,
                rc.reason,
                rc.date
            FROM tbl_rawMatChangelogs rc
            INNER JOIN tbl_rawMaterials rm ON rc.rawMaterialID = rm.rawMaterialID
            WHERE rc.reason LIKE 'Product Stock In%'
            AND rc.action = 'Decrease'
            {$rawDate}
            UNION ALL
            SELECT
                'Product Stock In' AS consumptionGroup,
                'Packaging' AS itemType,
                pk.packagingName AS itemName,
                pc.action,
                pc.quantity AS quantityConsumed,
                pk.packagingPrice AS unitPrice,
                (pc.quantity * pk.packagingPrice) AS assetValue,
                pk.unitType,
                pc.reason,
                pc.date
            FROM tbl_packagingChangelogs pc
            INNER JOIN tbl_packaging pk ON pc.packagingID = pk.packagingID
            WHERE pc.reason LIKE 'Product Stock In%'
            AND pc.action = 'Decrease'
            {$packagingDate}
            ORDER BY date DESC, itemType, itemName
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
                0 AS pendingQty,
                ((SUM(CASE WHEN o.orderStatus IN ('completed', 'late') THEN o.quantity ELSE 0 END)) * p.productPrice) AS totalRevenue,
                0 AS pendingRevenue,
                ((SUM(CASE WHEN o.orderStatus IN ('completed', 'late') THEN o.quantity ELSE 0 END)) * p.productPrice) AS expectedRevenue
            FROM tbl_orders o
            INNER JOIN tbl_products p ON o.productID = p.productID
            WHERE o.deleted_at IS NULL
            AND o.orderStatus IN ('completed', 'late')
            {$dateFilter}
            GROUP BY p.productID, p.productName, p.productPrice
            ORDER BY totalQtyOrdered DESC
        ");
    }

    private function getSalesDemandSummary($dateFrom, $dateUntil) {
        $dateFilter = $this->dateFilter('o.created_at', $dateFrom, $dateUntil);
        $rows = $this->fetchAll("
            SELECT
                COUNT(CASE WHEN o.orderStatus IN ('completed', 'late') THEN o.orderID ELSE NULL END) AS totalOrders,
                SUM(CASE WHEN o.orderStatus IN ('completed', 'late') THEN o.quantity * p.productPrice ELSE 0 END) AS totalRevenue,
                SUM(CASE WHEN o.orderStatus = 'pending' THEN o.quantity * p.productPrice ELSE 0 END) AS pendingRevenue,
                SUM(CASE WHEN o.orderStatus IN ('completed', 'late', 'pending') THEN o.quantity * p.productPrice ELSE 0 END) AS expectedRevenue
            FROM tbl_orders o
            INNER JOIN tbl_products p ON o.productID = p.productID
            WHERE o.deleted_at IS NULL
            AND o.orderStatus != 'canceled'
            {$dateFilter}
        ");

        return $rows[0] ?? [
            'totalOrders' => 0,
            'totalRevenue' => 0,
            'pendingRevenue' => 0,
            'expectedRevenue' => 0
        ];
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
