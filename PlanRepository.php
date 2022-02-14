<?php

namespace App\Repository;

use App\DBAL\Type\TimePeriod;
use App\Entity\User\Plan;
use App\Http\ParamConverter\RequestCollection;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Happyr\DoctrineSpecification\Spec;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * Class PlanRepository
 * @package App\Repository
 */
class PlanRepository extends CollectionRepository {

    /**
     * GenreRepository constructor.
     *
     * @param \Symfony\Bridge\Doctrine\RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry) {
        parent::__construct($registry, Plan::class);
    }

    /**
     * @param string $group
     *
     * @return \App\Entity\User\Plan|mixed
     */
    public function findMinPlanOfGroup(string $group) {
        $plans = $this->findBy(['groupName' => $group]);

        $minPlan = current($plans);
        /** @var Plan $plan */
        foreach ($plans as $plan) {
            if ($plan->getPrice() < $minPlan->getPrice()) {
                $minPlan = $plan;
            }
        }

        return $minPlan;
    }

    /**
     * @param string $group
     *
     * @return \App\Entity\User\Plan|mixed
     */
    public function findMinPlanByTimeOfGroup(string $group) {
        $plans = $this->findBy(['groupName' => $group]);

        $minPlan = current($plans);
        /** @var Plan $plan */
        foreach ($plans as $plan) {
            if ($plan->getPeriod() === TimePeriod::MONTH) {
                $minPlan = $plan;
            }
        }

        return $minPlan;
    }

    /**
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function createPlanQueryBuilder(): QueryBuilder {
        return $this->createQueryBuilder('p')
            ->orderBy('p.level', 'ASC');
    }

    /**
     * @param \App\Http\ParamConverter\RequestCollection $requestCollection
     *
     * @return \Doctrine\ORM\Tools\Pagination\Paginator
     */
    public function getPlans(RequestCollection $requestCollection): Paginator {
        $this->getSpec()->andX(
            Spec::andX(
                Spec::orderBy('level', 'ASC')
            )
        );

        return $this->getPaginator($requestCollection);
    }
}
