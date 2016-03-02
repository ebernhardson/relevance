<?php

namespace WikiMedia\RelevanceScoring\Repository;

use Doctrine\DBAL\Connection;
use PDO;
use PlasmaConduit\option\None;
use PlasmaConduit\option\Option;
use PlasmaConduit\option\Some;
use WikiMedia\OAuth\User;

class UsersRepository {

    private $db;

    public function __construct(Connection $db) {
        $this->db = $db;
    }

    public function updateUser(User $user) {
        $properties = [
            'name' => $user->name,
            'editcount' => $user->extra['editcount'],
        ];

        if ($this->userExists($user)) {
            $this->db->update('users', $properties, ['id' => $user->uid]);
        } else {
            $properties['id'] = $user->uid;
            $properties['created'] = time();
            $this->db->insert('users', $properties);
        }
    }

    public function userExists(User $user) {
        $sql = 'SELECT 1 FROM users WHERE id = ?';
        return $this->db->fetchColumn($sql, [$user->uid]) === "1";
    }

    /**
     * @param string $name
     * @return Option<User>
     */
    public function getUserByName($name) {
        return $this->createUserFromCondition('name = ?', [$name]);
    }

    /**
     * @param int $id
     * @return Option<User>
     */
    public function getUserById($name) {
        return $this->createUserFromCondition('id = ?', [$id], [PDO::PARAM_INT]);
    }

    private function createUserFromCondition($condition, $values, array $types = []) {
        $sql = "SELECT id, name, editcount FROM users WHERE $condition";
        $row = $this->db->fetchAssoc($sql, $values);
        if (!$row) {
            return new None();
        }

        $user = new User;
        $user->uid = (int) $row['id'];
        $user->name = $row['name'];
        $user->extra = ['editcount' => $row['editcount']];

        return new Some($user);
    }
}

