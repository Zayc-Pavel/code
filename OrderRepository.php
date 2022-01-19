<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;


/**
 * Class OrderRepository
 *
 * @package App\Repository
 */
class OrderRepository extends ServiceEntityRepository
{

    /**
     * @var \Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * OrderRepository constructor.
     *
     * @param \Symfony\Bridge\Doctrine\RegistryInterface $registry
     * @param \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface $tokenStorage
     */
    public function __construct(RegistryInterface $registry, TokenStorageInterface $tokenStorage)
    {
        parent::__construct($registry, Order::class);
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @return \Doctrine\ORM\Query
     */
    public function getAllQuery()
    {
        return $this->createQueryBuilder('w')->getQuery();
    }

    /**
     * @param \FOS\UserBundle\Model\UserInterface $user
     *
     * @return array
     */
    public function findAllByUser(UserInterface $user)
    {
        return $this->findBy(['user' => $user]);
    }

    /**
     * @param \FOS\UserBundle\Model\UserInterface $user
     *
     * @return \Doctrine\ORM\Query
     */
    public function getAllByUserQuery(UserInterface $user)
    {
        return $this->createQueryBuilder('o')
            ->select("o as order, MAX(tr.ts) as lastTransaction")
            ->leftJoin('o.buyTransactions', 'tr')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user)
            ->addGroupBy('o.orderId')
            ->orderBy('o.ts', 'DESC')
            ->getQuery();
    }

    /**
     * @param Order $order
     * @throws \Doctrine\ORM\ORMException
     */
    public function add(Order $order)
    {
        $this->_em->persist($order);
    }

    /**
     * @param \App\Entity\Order $order
     * @param string $type
     * @return mixed
     */
    public function getReverseOrders(Order $order, string $type) {
        $currentUser = $this->tokenStorage->getToken()->getUser();

        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.buyCurrencyCode = :sellCode')
            ->andWhere('o.sellCurrencyCode = :buyCode')
            ->andWhere('o.ts <= :ts')
            ->andWhere('o.restAmount > 0')
            ->andWhere('o.state in (0, 2)')
            ->andWhere('o.user != :currentUser')
            ->setParameter('sellCode', $order->getSellCurrencyCode())
            ->setParameter('buyCode', $order->getBuyCurrencyCode())
            ->setParameter('ts', $order->getTs())
            ->setParameter(':currentUser', $currentUser)
            ->addOrderBy('o.rate', 'ASC')
            ->addOrderBy('o.ts', 'ASC');

        if ($type == Order::TYPE_BUY) {
            $qb->andWhere('o.rate <= :rate');
        } else {
            $qb->andWhere('o.rate >= :rate');
        }

        $qb->setParameter('rate', $order->getRate());

        return $qb->getQuery()->getResult();
    }

    /**
     * @return mixed
     */
    public function findPricesOrders() {
        $pairs = $this->createQueryBuilder('o')
            ->select([
                'o.sellCurrencyCode',
                'o.buyCurrencyCode',
                'SUM(o.restAmount) as volume',
                'MIN(o.rate) as minPrice',
                'MAX(o.rate) as maxPrice',
                'MIN(o.type) as type'
            ])
            ->andWhere("o.state not in ('-1', '1')")
            ->addGroupBy('o.sellCurrencyCode' )
            ->addGroupBy('o.buyCurrencyCode')
            ->orderBy('minPrice', 'DESC')
            ->getQuery()->getResult();

        $rates = [];
        foreach ($pairs as $row) {
            $rates[$row['buyCurrencyCode'] . $row['sellCurrencyCode']] = $row;
        }

        return $rates;
    }

    /**
     * @param string $sellCurrencyCode
     * @param string $buyCurrencyCode
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findActualPrices(string $sellCurrencyCode, string $buyCurrencyCode) {
        return  $this->createQueryBuilder('o')
            ->select([
                'MIN(o.rate) as minPrice',
                'MAX(o.rate) as maxPrice',
            ])
            ->andWhere("o.state not in ('-1', '1')")
            ->andWhere("o.sellCurrencyCode = :sellCurrencyCode")
            ->andWhere("o.buyCurrencyCode = :buyCurrencyCode")
            ->addGroupBy('o.sellCurrencyCode' )
            ->addGroupBy('o.buyCurrencyCode')
            ->setParameter('sellCurrencyCode', $sellCurrencyCode)
            ->setParameter('buyCurrencyCode', $buyCurrencyCode)
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }
}