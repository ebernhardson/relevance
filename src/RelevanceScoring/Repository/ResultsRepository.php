<?php

namespace WikiMedia\RelevanceScoring\Repository;

use Doctrine\DBAL\Connection;
use PlasmaConduit\option\None;
use PlasmaConduit\option\Option;
use PlasmaConduit\option\Some;
use WikiMedia\OAuth\User;

class ResultsRepository {

    private $db;

    public function __construct(Connection $db) {
        $this->db = $db;
    }

    /**
     * @param User   $user
     * @param string|null $wiki
     * @return Option<int>
     */
    public function getRandomId(User $user, $wiki = null) {
        $qb = $this->db->createQueryBuilder()
            ->select('MAX(id)')
            ->from('results');
        if ($wiki !== null) {
            $qb->where('wiki = ?');
            $qb->setParameter(0, $wiki);
        }

        $maxId  = $qb->execute()->fetchColumn();
        if ( $maxId === false ) {
            return new None();
        }

        $rand = mt_rand(0,$maxId);
        $sql = <<<EOD
SELECT r.id 
  FROM results r 
  LEFT OUTER JOIN scores s
    ON r.id = s.result_id AND s.user_id = ? 
 WHERE r.id > ? 
   AND s.id IS NULL
 ORDER BY r.id ASC
EOD;
        $id  = $this->db->fetchColumn($sql, [$user->uid, $rand]);
        if ($id === false) {
            $sql = str_replace('>', '<=', $sql);
            $id  = $this->db->fetchColumn($sql, [$user->uid, $rand]);
            if ($id === false) {
                return new None();
            }
        }

        return new Some($id);
    }

    /**
     * @param int $queryId
     * @return int Number of results deleted
     */
    public function deleteResultsByQueryId($queryId) {
        return $this->db->delete(
            'results',
            ['query_id' => $queryId]
        );
    }

    /**
     * @param int $id
     * @return Option<array>
     */
    public function getQueryResult($id) {
        $sql = <<<EOD
SELECT wiki, query, namespace, title, snippet
  FROM results r
  JOIN queries q
    ON q.id = r.query_id
 WHERE r.id = ?
EOD;
        $result = $this->db->fetchAssoc($sql, [$id]);
        if ($result === false) {
            return new None();
        }

        return new Some($result);
    }

    /**
     * @param User|int              $user    User requesting the import
     * @param int                   $queryId Query id to attach results to
     * @param array<ImportedResult> $results Individual results to store
     */
    public function storeResults($user, $queryId, array $results ) {
        $userId = $user instanceof User ? $user->uid : $user;
        $now = time();
        foreach ($results as $result) {
            $this->db->insert('results', [
                'user_id'  => $userId,
                'query_id' => $queryId,
                'title'    => $result->getTitle(),
                'snippet'  => $result->getSnippet(),
                'source'   => $result->getSource(),
                'position' => $result->getPosition(),
                'created'  => $now,
            ]);
        }
    }
}

