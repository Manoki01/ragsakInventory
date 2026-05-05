<?php

require_once __DIR__ . '/../config/database.php';

class Dashboard {
    private $conn;

    public function __construct() {
        $this->conn = Database::connect();
    }

    private function fetchAll($query) {
        $result = $this->conn->query($query);

        if (!$result) {
            throw new Exception($this->conn->error);
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function fetchOne($query) {
        $rows = $this->fetchAll($query);
        return $rows[0] ?? [];
    }

    public function getDataset() {
        return [
            'stats' => $this->getStats(),
            'transactions' => $this->getTransactions(),
            'changelogs' => $this->getChangelogs()
        ];
    }

    private function getStats() {
        return [
            'products' => $this->fetchOne("
                SELECT
                    (SELECT COUNT(productID) FROM tbl_products WHERE deleted_at IS NULL) AS totalEntries,
                    COUNT(DISTINCT CASE WHEN ps.quantity > 0 AND ps.quantity <= 15 THEN pf.flowID END) AS lowStock,
                    COUNT(DISTINCT CASE WHEN COALESCE(ps.quantity, 0) <= 0 THEN pf.flowID END) AS noStock
                FROM tbl_processFlow pf
                INNER JOIN tbl_products p ON pf.productID = p.productID
                INNER JOIN tbl_process pr ON pf.processID = pr.processID
                LEFT JOIN tbl_productStock ps ON pf.flowID = ps.flowID
                WHERE p.deleted_at IS NULL
                AND pr.deleted_at IS NULL
            "),
            'rawMaterials' => $this->fetchOne("
                SELECT
                    COUNT(rawMaterialID) AS totalEntries,
                    SUM(CASE WHEN quantity > 0 AND quantity <= 15 THEN 1 ELSE 0 END) AS lowStock,
                    SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END) AS noStock
                FROM tbl_rawMaterials
                WHERE deleted_at IS NULL
            "),
            'packaging' => $this->fetchOne("
                SELECT
                    COUNT(packagingID) AS totalEntries,
                    SUM(CASE WHEN quantity > 0 AND quantity <= 50 THEN 1 ELSE 0 END) AS lowStock,
                    SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END) AS noStock
                FROM tbl_packaging
                WHERE deleted_at IS NULL
            ")
        ];
    }

    private function getTransactions() {
        return $this->fetchAll("
            SELECT
                'Products' AS moduleType,
                COALESCE(u.username, CONCAT('User #', pt.userID)) AS username,
                p.productName AS recordName,
                pr.processName AS groupName,
                NULL AS orderID,
                pt.action,
                pt.quantity,
                pt.status,
                pt.date
            FROM tbl_prodTransactions pt
            INNER JOIN tbl_processFlow pf ON pt.flowID = pf.flowID
            INNER JOIN tbl_products p ON pf.productID = p.productID
            INNER JOIN tbl_process pr ON pf.processID = pr.processID
            LEFT JOIN tbl_user u ON pt.userID = u.userID
            UNION ALL
            SELECT
                'Raw Materials' AS moduleType,
                COALESCE(u.username, CONCAT('User #', rt.userID)) AS username,
                rm.rawMaterialName AS recordName,
                rm.matType AS groupName,
                NULL AS orderID,
                rt.action,
                rt.quantity,
                rt.status,
                rt.date
            FROM tbl_rawMatTransactions rt
            INNER JOIN tbl_rawMaterials rm ON rt.rawMaterialID = rm.rawMaterialID
            LEFT JOIN tbl_user u ON rt.userID = u.userID
            UNION ALL
            SELECT
                'Packaging' AS moduleType,
                COALESCE(u.username, CONCAT('User #', pkt.userID)) AS username,
                pk.packagingName AS recordName,
                pk.packagingType AS groupName,
                NULL AS orderID,
                pkt.action,
                pkt.quantity,
                pkt.status,
                pkt.date
            FROM tbl_packagingTransactions pkt
            INNER JOIN tbl_packaging pk ON pkt.packagingID = pk.packagingID
            LEFT JOIN tbl_user u ON pkt.userID = u.userID
            UNION ALL
            SELECT
                'Orders' AS moduleType,
                COALESCE(u.username, CONCAT('User #', ot.userID)) AS username,
                CONCAT('Order #', ot.orderID, ' - ', COALESCE(p.productName, 'Unknown Product')) AS recordName,
                COALESCE(o.orderStatus, 'order') AS groupName,
                ot.orderID,
                ot.action,
                o.quantity,
                o.orderStatus AS status,
                ot.date
            FROM tbl_orderTransactions ot
            LEFT JOIN tbl_orders o ON ot.orderID = o.orderID
            LEFT JOIN tbl_products p ON o.productID = p.productID
            LEFT JOIN tbl_user u ON ot.userID = u.userID
            ORDER BY date DESC
            LIMIT 200
        ");
    }

    private function getChangelogs() {
        return $this->fetchAll("
            SELECT
                'Products' AS moduleType,
                p.productName AS recordName,
                pr.processName AS groupName,
                NULL AS orderID,
                pc.action,
                pc.quantity,
                pc.initialQuantity AS initialValue,
                pc.finalQuantity AS finalValue,
                pc.reason,
                pc.date
            FROM tbl_prodChangelogs pc
            INNER JOIN tbl_processFlow pf ON pc.flowID = pf.flowID
            INNER JOIN tbl_products p ON pf.productID = p.productID
            INNER JOIN tbl_process pr ON pf.processID = pr.processID
            UNION ALL
            SELECT
                'Raw Materials' AS moduleType,
                rm.rawMaterialName AS recordName,
                rm.matType AS groupName,
                NULL AS orderID,
                rc.action,
                rc.quantity,
                rc.initialQuantity AS initialValue,
                rc.finalQuantity AS finalValue,
                rc.reason,
                rc.date
            FROM tbl_rawMatChangelogs rc
            INNER JOIN tbl_rawMaterials rm ON rc.rawMaterialID = rm.rawMaterialID
            UNION ALL
            SELECT
                'Packaging' AS moduleType,
                pk.packagingName AS recordName,
                pk.packagingType AS groupName,
                NULL AS orderID,
                pcg.action,
                pcg.quantity,
                pcg.initialQuantity AS initialValue,
                pcg.finalQuantity AS finalValue,
                pcg.reason,
                pcg.date
            FROM tbl_packagingChangelogs pcg
            INNER JOIN tbl_packaging pk ON pcg.packagingID = pk.packagingID
            UNION ALL
            SELECT
                'Orders' AS moduleType,
                CONCAT('Order #', oc.orderID, ' - ', COALESCE(p.productName, 'Unknown Product')) AS recordName,
                COALESCE(o.orderStatus, 'order') AS groupName,
                oc.orderID,
                oc.action,
                NULL AS quantity,
                oc.initialValue,
                oc.finalValue,
                oc.action AS reason,
                oc.date
            FROM tbl_orderChangelogs oc
            LEFT JOIN tbl_orders o ON oc.orderID = o.orderID
            LEFT JOIN tbl_products p ON o.productID = p.productID
            ORDER BY date DESC
            LIMIT 200
        ");
    }
}
