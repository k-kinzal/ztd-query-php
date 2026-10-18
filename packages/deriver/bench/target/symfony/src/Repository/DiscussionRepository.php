<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{Board,Post,Topic};
use App\Enum\TopicState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class DiscussionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Topic::class);
    }
    public function page(Board $board, int $before, string $search = ''): array
    {
        $query = $this->createQueryBuilder('t')->addSelect('a')->join('t.author', 'a')->where('t.board=:board')->andWhere('t.id<:before')->andWhere('t.status IN (:states)')->setParameter('board', $board)->setParameter('before', $before)->setParameter('states', [TopicState::Published->value,TopicState::Locked->value,TopicState::Archived->value])->orderBy('t.id', 'DESC')->setMaxResults(30);
        if ($search !== '') {
            $query->andWhere('LOWER(t.title) LIKE :search')->setParameter('search', '%'.mb_strtolower(addcslashes($search, '%_\\')).'%');
        }
        return $query->getQuery()->getResult();
    }
    public function posts(Topic $topic, int $after): array
    {
        return $this->getEntityManager()->createQueryBuilder()->select('p', 'a')->from(Post::class, 'p')->join('p.author', 'a')->where('p.topic=:topic')->andWhere('p.hiddenAt IS NULL')->andWhere('p.id>:after')->setParameter('topic', $topic)->setParameter('after', $after)->orderBy('p.id', 'ASC')->setMaxResults(50)->getQuery()->getResult();
    }
}
