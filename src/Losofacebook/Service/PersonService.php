<?php

namespace Losofacebook\Service;
use Doctrine\DBAL\Connection;
use Losofacebook\Person;
use DateTime;
use Memcached;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Image service
 */
class PersonService extends AbstractService
{
    private $memcached;
    private $timing = 60;
    public function __construct(Connection $conn, Memcached $memcached)
    {
        parent::__construct($conn, 'person');
        $this->memcached = $memcached;
    }


    /**
     * @param $username
     * @param bool $findFriends
     * @return Person
     */
    public function findByUsername($username, $findFriends = true)
    {
        return $this->findBy(['username' => $username], [], $findFriends)->current();
    }
    
    public function findById($id, $findFriends = true)
    {
        return $this->findBy(['id' => $id], [], $findFriends)->current();
    }

    /**
     * @param array $params
     */
    public function findBy(array $params = [], $options = [], $fetchFriends = true)
    {
        return parent::findByParams($params, $options, function ($data) use ($fetchFriends) {
            return $this->createPerson($data, $fetchFriends);
        });
    }

    public function findFriends($id)
    {

        $cacheID = "friend_{$id}";
        
        if($friends = $this->memcached->get($cacheID)){
            return $friends;
        }
        $params['id'] = $this->findFriendIds($id);

        $json = $this->findBy($params, ['orderBy' => ['last_name ASC', 'first_name ASC']], false);

        $this->memcached->set($cacheID, $json, $timing);

        return $json;

    }

    /**
     * @param $personId
     * @param array $params
     * @return \ArrayIterator
     */
    public function findFriendsBy($personId, $params = [])
    {
        $now = new DateTime();

        $person = $this->findByUsername($personId, true);
        
        $params['id'] = $this->findFriendIds($person->getId());
        if (isset($params['birthday'])) {
            $params['MONTH(birthday)'] = $now->format('m');
            $params['DAY(birthday)'] = $now->format('d');
            unset($params['birthday']);
           
        }

        return $this->findBy($params, ['orderBy' => ['last_name ASC', 'first_name ASC']], false);
    }


    public function findFriendIds($id)
    {   $cacheID = "friend_id_{$id}";
        if($ids = $this->memcached->get($cacheID)){
            return $ids;
        }
        $myAdded = $this->conn->fetchAll(
            "SELECT target_id FROM friendship WHERE source_id = ?",
            [$id]
        );

        $meAdded = $this->conn->fetchAll(
            "SELECT source_id FROM friendship WHERE target_id = ?",
            [$id]
        );

        $myAdded = array_reduce($myAdded, function ($result, $row) {
            $result[] = $row['target_id'];
            return $result;
        }, []);

        $meAdded = array_reduce($meAdded, function ($result, $row) {
            $result[] = $row['source_id'];
            return $result;
        }, []);
        $ret = array_unique(array_merge($myAdded, $meAdded));
         $this->memcached->set($cacheID, $ret, $timing);
        return $ret;
    }

    /**
     * @param $data
     * @param $fetchFriends
     * @return Person
     */
    protected function createPerson($data, $fetchFriends)
    {   
        $person = Person::create($data);
        if ($fetchFriends) {
            $person->setFriends($this->findFriends($person->getId()));
        }
        return $person;
    }
}
