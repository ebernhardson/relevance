<?php

namespace WikiMedia\RelevanceScoring\Repository;

use Doctrine\DBAL\Connection;
use PDO;
use PlasmaConduit\option\None;
use PlasmaConduit\option\Option;
use PlasmaConduit\option\Some;
use WikiMedia\OAuth\User;

class QueriesRepository {

    private $db;

    public function __construct(Connection $db) {
        $this->db = $db;
    }

    /**
     * @param string $wiki
     * @param string $query
     * @return Option<int>
     */
    public function findQueryId($wiki, $query) {
        $result = $this->db->fetchColumn(
            'SELECT id FROM queries WHERE wiki = ? AND query = ?',
            [$wiki, $query]
        );

        if ($result) {
            return new Some($result);
        } else {
            return new None();
        }
    }

    public function createQuery(User $user, $wiki, $query, $imported = false) {
        $inserted = $this->db->insert('queries', [
            'user_id' => $user->uid,
            'wiki' => $wiki,
            'query' => $query,
            'created' => time(),
            'imported' => $imported === 'imported',
        ]);
        if (!$inserted) {
            throw new \Exception('Failed insert new query');
        }

        return $this->db->lastInsertId();
    }

    public function markQueryImported($queryId ) {
        $affected = $this->db->update(
            'queries',
            ['imported' => true],
            ['id' => $queryId]
        );

        return $affected === 1;
    }

    public function deleteQueryById($queryId) {
        $affected = $this->db->delete(
            'queries',
            ['id' => $queryId]
        );

        return $affected === 1;
    }

    public function getPendingQueries($limit, $userId = null) {
        $sql = 'SELECT * FROM queries WHERE imported = 0';
        $params = [];
        $types = [];
        if ($userId !== null) {
            $sql .= ' WHERE user_id = ?';
            $params[] = $userId;
            $types[] = PDO::PARAM_INT;
        }
        $sql .= ' LIMIT ?';
        $params[] = $limit;
        $types[] = PDO::PARAM_INT;

        return $this->db->fetchAll($sql, $params, $types);
    }
}

