<?php

namespace App\Repository;

use App\Base\BaseRepository;
use App\Entity\Answer;
use App\Entity\Call;
use App\Entity\Campaign;
use App\Entity\Choice;
use App\Entity\Message;
use App\Entity\Selection;
use App\Entity\Volunteer;
use App\Tools\Random;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\Mapping\MappingException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;

class MessageRepository extends BaseRepository
{
    const CODE_SIZE = 8;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @param string $phoneNumber
     * @param string $prefix
     *
     * @return Message|null
     *
     * @throws NonUniqueResultException
     */
    public function getMessageFromPhoneNumber(string $phoneNumber): ?Message
    {
        return $this->createQueryBuilder('m')
                    ->join('m.volunteer', 'v')
                    ->join('m.communication', 'co')
                    ->join('co.campaign', 'ca')
                    ->where('v.phoneNumber = :phoneNumber')
                    ->andWhere('ca.active = true')
                    ->orderBy('m.id', 'DESC')
                    ->setMaxResults(1)
                    ->setParameter('phoneNumber', $phoneNumber)
                    ->getQuery()
                    ->getOneOrNullResult();
    }

    /**
     * @param string $phoneNumber
     * @param string $prefix
     *
     * @return Message|null
     *
     * @throws NonUniqueResultException
     */
    public function getMessageFromPhoneNumberAndPrefix(string $phoneNumber, string $prefix): ?Message
    {
        return $this->createQueryBuilder('m')
                    ->join('m.volunteer', 'v')
                    ->join('m.communication', 'co')
                    ->join('co.campaign', 'ca')
                    ->where('v.phoneNumber = :phoneNumber')
                    ->setParameter('phoneNumber', $phoneNumber)
                    ->andWhere('m.prefix = :prefix')
                    ->setParameter('prefix', $prefix)
                    ->andWhere('ca.active = true')
                    ->orderBy('m.id', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
    }

    /**
     * @param Message $message
     * @param Choice  $choice
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function cancelAnswerByChoice(Message $message, Choice $choice): void
    {
        foreach ($message->getAnswers() as $answer) {
            /* @var Answer $answer */
            if ($answer->getChoices()->removeElement($choice)) {
                $this->_em->persist($answer);
            }
        }

        $this->_em->flush();
    }

    /**
     * @param int $messageId
     *
     * @return Message|null
     */
    public function findOneByIdNoCache(int $messageId): ?Message
    {
        return $this->createQueryBuilder('m')
                    ->where('m.id = :id')
                    ->setParameter('id', $messageId)
                    ->getQuery()
                    ->useResultCache(false)
                    ->getOneOrNullResult();
    }

    /**
     * @param Message $message
     *
     * @return Message|null
     * @throws MappingException
     */
    public function refresh(Message $message): Message
    {
        $this->_em->clear();

        return $this->findOneByIdNoCache($message->getId());
    }

    /**
     * @param Campaign $campaign
     *
     * @return int
     */
    public function getNumberOfSentMessages(Campaign $campaign): int
    {
        return $this->createQueryBuilder('m')
                    ->select('COUNT(m.id)')
                    ->join('m.communication', 'co')
                    ->join('co.campaign', 'ca')
                    ->where('ca.id = :campaignId')
                    ->andWhere('m.messageId IS NOT NULL')
                    ->setParameter('campaignId', $campaign->getId())
                    ->getQuery()
                    ->useResultCache(false)
                    ->getSingleScalarResult();
    }

    /**
     * Infinite loop risk?
     * POW(62, 8) = 218 340 105 584 896
     * we're safe.
     */
    public function generateCode(string $column = 'code'): string
    {
        do {
            $code = Random::generate(self::CODE_SIZE);

            if (null === $this->findOneBy([$column => $code])) {
                break;
            }
        } while (true);

        return $code;
    }

    /**
     * @param Volunteer $volunteer
     * @param string    $prefix
     */
    public function getMessageFromVolunteer(Volunteer $volunteer, string $prefix)
    {
        return $this->createQueryBuilder('m')
                    ->join('m.communication', 'co')
                    ->join('co.campaign', 'ca')
                    ->where('ca.active = true')
                    ->andWhere('m.volunteer = :volunteer')
                    ->andWhere('m.prefix = :prefix')
                    ->setParameter('volunteer', $volunteer)
                    ->setParameter('prefix', $prefix)
                    ->getQuery()
                    ->getOneOrNullResult();
    }

    /**
     * @param array $volunteersTakenPrefixes
     *
     * @return bool
     */
    public function canUsePrefixesForEveryone(array $volunteersTakenPrefixes): bool
    {
        if (!$volunteersTakenPrefixes) {
            return true;
        }

        $qb = $this->createQueryBuilder('m')
                   ->select('COUNT(m.id)')
                   ->join('m.communication', 'co')
                   ->join('co.campaign', 'ca')
                   ->join('m.volunteer', 'v')
                   ->where('ca.active = true')
                   ->andWhere('v.id IN (:volunteerIds)')
                   ->setParameter('volunteerIds', array_keys($volunteersTakenPrefixes));

        // Simulating CASE WHEN THEN END
        $orXs = [];
        foreach ($volunteersTakenPrefixes as $volunteerId => $prefixes) {
            $orXs[] = sprintf('v.id = :v_%d AND m.prefix IN (:p_%d)', $volunteerId, $volunteerId);
            $qb->setParameter(sprintf('v_%d', $volunteerId), $volunteerId);
            $qb->setParameter(sprintf('p_%d', $volunteerId), $prefixes);
        }

        $result = (bool)$qb
            ->andWhere(call_user_func_array([$qb->expr(), 'orX'], $orXs))
            ->getQuery()
            ->getSingleScalarResult();

        return !$result;
    }

    /**
     * Return all sent messages and group by kind (communication.type)
     *
     * @param \DateTime $from
     * @param \DateTime $to
     * @return array
     */
    public function getNumberOfSentMessagesByKind(\DateTime $from, \DateTime $to)
    {
        $sql = 'select c.type, count(*)
                from message m join communication c
                on m.communication_id = c.id
                where c.created_at > :fromDate
                and c.created_at < :toDate
                group by c.type;';

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('type', 'type')
            ->addScalarResult('count(*)', 'count', 'integer');

        return $this->_em
            ->createNativeQuery($sql, $rsm)
            ->setParameter('fromDate', $from)
            ->setParameter('toDate', $to)
            ->getResult();
    }

    /**
     * Return all triggered volounteers
     * @return Message|null
     *
     * @param \DateTime $from
     * @param \DateTime $to
     * @return array
     * @throws NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function getNumberOfTriggeredVolounteers(\DateTime $from, \DateTime $to)
    {
        $sql = 'select count(distinct m.volunteer_id) volounteers
                from message m
                join communication c on m.communication_id = c.id
                where c.created_at > :fromDate
                and c.created_at <= :toDate;';

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('volounteers', 'volounteers', 'integer');

        return $this->_em
            ->createNativeQuery($sql, $rsm)
            ->setParameter('fromDate', $from)
            ->setParameter('toDate', $to)
            ->getSingleResult();
    }

    /**
     * Return all answers received
     *
     * @return QueryBuilder
     * @throws NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function getNumberOfAnswersReceived(\DateTime $from, \DateTime $to)
    public function getLatestMessageUpdated(): ?Message
    {
        $sql = 'select count(*) answers from message m
                join communication c on m.communication_id = c.id
                join answer a on a.message_id = m.id
                where c.created_at > :fromDate
                and c.created_at <= :toDate';

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('answers', 'answers', 'integer');

        return $this->_em
            ->createNativeQuery($sql, $rsm)
            ->setParameter(':fromDate', $from)
            ->setParameter(':toDate', $to)
            ->getSingleResult();
        try {
            return $this->createQueryBuilder('m')
                ->orderBy('m.updatedAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleResult();
        } catch (NoResultException $e) {
            return null;
        }
    }

    /**
     * Return all sent messages and group by kind (communication.type)
     *
     * @param \DateTime $from
     * @param \DateTime $to
     * @return array
     */
    public function getNumberOfSentMessagesByKind(\DateTime $from, \DateTime $to)
    {
        $sql = 'select c.type, count(*)
                from message m join communication c
                on m.communication_id = c.id
                where c.created_at > :fromDate
                and c.created_at < :toDate
                group by c.type;';

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('type', 'type')
            ->addScalarResult('count(*)', 'count', 'integer');

        return $this->_em
            ->createNativeQuery($sql, $rsm)
            ->setParameter('fromDate', $from)
            ->setParameter('toDate', $to)
            ->getResult();
    }

    /**
     * Return all triggered volounteers
     *
     * @param \DateTime $from
     * @param \DateTime $to
     * @return array
     * @throws NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function getNumberOfTriggeredVolounteers(\DateTime $from, \DateTime $to)
    {
        $sql = 'select count(distinct m.volunteer_id) volounteers
                from message m
                join communication c on m.communication_id = c.id
                where c.created_at > :fromDate
                and c.created_at <= :toDate;';

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('volounteers', 'volounteers', 'integer');

        return $this->_em
            ->createNativeQuery($sql, $rsm)
            ->setParameter('fromDate', $from)
            ->setParameter('toDate', $to)
            ->getSingleResult();
    }

    /**
     * Return all answers received
     *
     * @return QueryBuilder
     * @throws NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function getNumberOfAnswersReceived(\DateTime $from, \DateTime $to)
    {
        $sql = 'select count(*) answers from message m
                join communication c on m.communication_id = c.id
                join answer a on a.message_id = m.id
                where c.created_at > :fromDate
                and c.created_at <= :toDate';

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('answers', 'answers', 'integer');

        return $this->_em
            ->createNativeQuery($sql, $rsm)
            ->setParameter(':fromDate', $from)
            ->setParameter(':toDate', $to)
            ->getSingleResult();
    }
}
