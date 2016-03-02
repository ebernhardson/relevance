<?php

namespace WikiMedia\RelevanceScoring\Repository;

use Doctrine\DBAL\Connection;
use WikiMedia\OAuth\User;

class ScoresRepository {

    private $db;

    public function __construct(Connection $db) {
        $this->db = $db;
    }

    public function storeQueryScore(User $user, $id, $score) {
        $this->db->insert('scores', [
            'result_id' => $id,
            'score' => $score,
            'created' => time(),
            'user_id' => $user->uid,
        ]);
    }

    public function getAll() {
        $sql = <<<EOD
SELECT AVG(s.score) as score,
       COUNT(1) as num_scores,
       q.wiki as wiki,
       q.query as query,
       r.title as title,
       r.namespace as namespace
  FROM scores s
  JOIN results r ON r.id = s.result_id
  JOIN queries q ON q.id = r.query_id
 GROUP BY r.id
 ORDER BY q.wiki, q.id, AVG(s.score) DESC
EOD;
        return $this->db->fetchAll($sql);
    }

    // @todo make not suck
    public function getScoresForWiki($wiki, $query=null) {
        $qb = $this->db->createQueryBuilder()
            ->select('wiki', 'query', 'created', 'score')
            ->from('scores', 's')
            ->innerJoin('s', 'results', 'r', 'r.id = s.result_id')
            ->innerJoin('r', 'queries', 'q', 'q.id = r.query_id')
            ->where('q.wiki = ?')
            ->setPositionalParam(0, $wiki);

        if ($query !== null) {
            $qb->andWhere('q.query = ?')
                ->setPositionalParam(1, $query);
        }

        $res = $qb->execute()->fetchAll();
        if ($res === false) {
            throw new \Exception('Query Failure');
        }
        return $res;
    }

    /**
     * @param int $queryId
     * @return int Number of scores deleted
     */
    public function deleteScoresByQueryId($queryId) {
        $sql = 'DELETE FROM scores USING scores, results WHERE scores.result_id = results.id AND results.query_id = ?';
        return $this->db->executeUpdate($sql,[$queryId]);
    }
}

