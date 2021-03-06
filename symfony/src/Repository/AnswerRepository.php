<?php

namespace App\Repository;

use App\Base\BaseRepository;
use App\Entity\Answer;
use App\Entity\Campaign;
use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

/**
 * @method Answer|null find($id, $lockMode = null, $lockVersion = null)
 * @method Answer|null findOneBy(array $criteria, array $orderBy = null)
 * @method Answer[]    findAll()
 * @method Answer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AnswerRepository extends BaseRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Answer::class);
    }

    /**
     * @param Campaign $campaign
     *
     * @return int|null
     */
    public function getLastCampaignUpdateTimestamp(Campaign $campaign): ?int
    {
        $lastAnswer = $this->createQueryBuilder('a')
                           ->join('a.message', 'm')
                           ->join('m.communication', 'co')
                           ->join('co.campaign', 'ca')
                           ->where('ca.id = :campaignId')
                           ->setParameter('campaignId', $campaign->getId())
                           ->orderBy('a.updatedAt', 'DESC')
                           ->setMaxResults(1)
                           ->getQuery()
                           ->useResultCache(false)
                           ->getOneOrNullResult();

        if ($lastAnswer) {
            $this->_em->detach($lastAnswer);

            /* @var Answer $lastAnswer */
            return $lastAnswer->getUpdatedAt()->getTimestamp();
        }

        return null;
    }

    /**
     * @param Message $message
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function clearAnswers(Message $message)
    {
        foreach ($message->getAnswers() as $answer) {
            /* @var Answer $answer */
            $answer->getChoices()->clear();
            $this->_em->persist($answer);
        }

        $this->_em->flush();
    }

    /**
     * @param Message $message
     * @param array   $choices
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function clearChoices(Message $message, array $choices)
    {
        foreach ($choices as $choice) {
            if ($answer = $message->getAnswerByChoice($choice)) {
                $answer->getChoices()->removeElement($choice);
                $this->_em->persist($answer);
            }
        }

        $this->_em->flush();
    }
}
