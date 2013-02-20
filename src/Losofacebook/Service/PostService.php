<?php

namespace Losofacebook\Service;
use Doctrine\DBAL\Connection;
use Losofacebook\Post;
use Losofacebook\Comment;
use Losofacebook\Service\PersonService;
use DateTime;
use Memcached;

/**
 * Image service
 */
class PostService
{
    /**
     * @var Connection
     */
    private $conn;

    /**
     * @var PersonService
     */
    private $personService;

    /**
     * @param $basePath
     */
    private $memcached;
    public function __construct(Connection $conn, PersonService $personService, Memcached $memcached)
    {
        $this->conn = $conn;
        $this->personService = $personService;
        $this->memcached = $memcached;
    }

    /**
     * @param int $personId
     * @param \stdClass $data
     * @return Post
     */
    public function create($personId, $data)
    {
        $data = [
            'person_id' => $personId,
            'poster_id' => $data->poster->id,
            'date_created' => (new DateTime())->format('Y-m-d H:i:s'),
            'content' => $data->content,
        ];

        $this->conn->insert('post', $data);
        $data['id'] = $this->conn->lastInsertId();

        $post = Post::create($data);
        $post->setPerson($this->personService->findById($data['poster_id'], false));
        return $post;
    }

    /**
     * @param int $postId
     * @param \stdClass $data
     * @return Comment
     */
    public function createComment($postId, $data)
    {
        try {

        $data = [
            'post_id' => $postId,
            'poster_id' => $data->poster->id,
            'date_created' => (new DateTime())->format('Y-m-d H:i:s'),
            'content' => $data->content,
        ];
            $this->conn->insert('comment', $data);

            $data['id'] = $this->conn->lastInsertId();

            $comment = Comment::create($data);
            $comment->setPoster($this->personService->findById($data['poster_id'], false));
            return $comment;

        } catch (\Exception $e) {
            echo $e;
            die();
        }

    }


    /**
     * Finds by person id
     *
     * @param $path
     */
    public function findByPersonId($personId)
    {  $cacheID = "post_{$personId}";
        
        if($cpost = $this->memcached->get($cacheID)){
            return $cpost;
        }
        $data = $this->conn->fetchAll(
            "SELECT * FROM post WHERE person_id = ? ORDER BY date_created DESC", [$personId]
        );

        $posts = [];
        foreach ($data as $row) {


            $post = Post::create($row);
            $post->setPerson($this->personService->findById($row['poster_id'], false));
            $post->setComments($this->getComments($row['id']));

            $posts[] = $post;
        }
        $this->memcached->set($cacheID, $posts, 60);
        return $posts;
    }

    public function findFriends($id)
    {
        $friends = [];
        foreach ($this->findFriendIds($id) as $friendId) {
            $friends[] = $this->findById($friendId, false);
        }
        return $friends;
    }


    public function findFriendIds($id)
    {
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

        return array_unique(array_merge($myAdded, $meAdded));
    }


    public function getComments($postId)
    {
        $data = $this->conn->fetchAll(
            "SELECT * FROM comment WHERE post_id = ? ORDER BY date_created DESC", [$postId]
        );

        $comments = [];
        foreach ($data as $row) {
            $comment = Comment::create($row);
            $comment->setPoster($this->personService->findById($row['poster_id'], false));
            $comments[] = $comment;
        }
        return $comments;
    }
}
