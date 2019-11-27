<?php

namespace App\Repository;

use App\Base\BaseRepository;
use App\Entity\Campaign;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Campaign|null find($id, $lockMode = null, $lockVersion = null)
 * @method Campaign|null findOneBy(array $criteria, array $orderBy = null)
 * @method Campaign[]    findAll()
 * @method Campaign[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CampaignRepository extends BaseRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Campaign::class);
    }

    /**
     * @param int $campaignId
     *
     * @return Campaign|null
     */
    public function findOneByIdNoCache(int $campaignId)
    {
        $this->_em->clear();

        return $this->createQueryBuilder('c')
                    ->where('c.id = :id')
                    ->setParameter('id', $campaignId)
                    ->getQuery()
                    ->useResultCache(false)
                    ->getOneOrNullResult();
    }

    /**
     * @return QueryBuilder
     */
    public function getActiveCampaignsQueryBuilder(): QueryBuilder
    {
       return $this
            ->createQueryBuilder('c')
            ->where('c.active = 1');
    }

    /**
     * @return QueryBuilder
     */
    public function getInactiveCampaignsQueryBuilder(): QueryBuilder
    {
        return $this
            ->createQueryBuilder('c')
            ->where('c.active = 0');
    }

    /**
     * @param Campaign $campaign
     */
    public function closeCampaign(Campaign $campaign)
    {
        $campaign->setActive(false);

        $this->save($campaign);
    }

    /**
     * @param Campaign $campaign
     */
    public function openCampaign(Campaign $campaign)
    {
        $campaign->setActive(true);

        $this->save($campaign);
    }


    /**
     * @param Campaign $campaign
     * @param string   $color
     */
    public function changeColor(Campaign $campaign, string $color)
    {
        $campaign->setType($color);

        $this->save($campaign);
    }

    /**
     * @param Campaign $campaign
     * @param string   $newName
     */
    public function changeName(Campaign $campaign, string $newName)
    {
        $campaign->setLabel($newName);

        $this->save($campaign);
    }
}
