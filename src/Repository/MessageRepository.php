<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function findByConversation(Conversation $conversation, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.conversation = :conv')
            ->setParameter('conv', $conversation)
            ->orderBy('m.createdAt', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->join('m.conversation', 'c')
            ->where('(c.userOne = :user OR c.userTwo = :user)')
            ->andWhere('m.sender != :user')
            ->andWhere('m.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markConversationReadForUser(Conversation $conversation, User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->update()
            ->set('m.isRead', 'true')
            ->where('m.conversation = :conv')
            ->andWhere('m.sender != :user')
            ->andWhere('m.isRead = false')
            ->setParameter('conv', $conversation)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * @param Conversation[] $conversations
     * @return array<int, Message> keyed by conversation ID
     */
    public function findLastMessageForConversations(array $conversations): array
    {
        if (empty($conversations)) {
            return [];
        }

        $ids = array_map(fn(Conversation $c) => $c->getId(), $conversations);

        $messages = $this->createQueryBuilder('m')
            ->where('m.conversation IN (:ids)')
            ->andWhere('m.createdAt = (SELECT MAX(m2.createdAt) FROM App\Entity\Message m2 WHERE m2.conversation = m.conversation)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($messages as $msg) {
            $map[$msg->getConversation()->getId()] = $msg;
        }

        return $map;
    }
}
