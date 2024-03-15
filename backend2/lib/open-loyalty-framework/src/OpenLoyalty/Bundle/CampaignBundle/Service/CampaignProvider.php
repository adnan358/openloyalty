<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */
namespace OpenLoyalty\Bundle\CampaignBundle\Service;

use Broadway\ReadModel\Repository;
use OpenLoyalty\Component\Campaign\Domain\Campaign;
use OpenLoyalty\Component\Campaign\Domain\CampaignRepository;
use OpenLoyalty\Component\Campaign\Domain\CustomerId;
use OpenLoyalty\Component\Campaign\Domain\LevelId;
use OpenLoyalty\Component\Campaign\Domain\Model\Coupon;
use OpenLoyalty\Component\Campaign\Domain\ReadModel\CampaignUsage;
use OpenLoyalty\Component\Campaign\Domain\ReadModel\CampaignUsageRepository;
use OpenLoyalty\Component\Campaign\Domain\ReadModel\CouponUsage;
use OpenLoyalty\Component\Campaign\Domain\ReadModel\CouponUsageRepository;
use OpenLoyalty\Component\Campaign\Domain\SegmentId;
use OpenLoyalty\Component\Customer\Domain\ReadModel\CustomerDetails;
use OpenLoyalty\Component\Customer\Domain\ReadModel\CustomersBelongingToOneLevel;
use OpenLoyalty\Component\Segment\Domain\ReadModel\SegmentedCustomers;

/**
 * Class CampaignProvider.
 */
class CampaignProvider
{
    /**
     * @var Repository
     */
    protected $segmentedCustomersRepository;

    /**
     * @var Repository
     */
    protected $customerBelongingToOneLevelRepository;

    /**
     * @var CouponUsageRepository
     */
    protected $couponUsageRepository;

    /**
     * @var CampaignValidator
     */
    protected $campaignValidator;

    /**
     * @var CampaignUsageRepository
     */
    private $campaignUsageRepository;

    /**
     * @var CampaignRepository
     */
    private $campaignRepository;

    /**
     * CampaignCustomersProvider constructor.
     *
     * @param Repository              $segmentedCustomersRepository
     * @param Repository              $customerBelongingToOneLevelRepository
     * @param CouponUsageRepository   $couponUsageRepository
     * @param CampaignValidator       $campaignValidator
     * @param CampaignUsageRepository $campaignUsageRepository
     * @param CampaignRepository      $campaignRepository
     */
    public function __construct(
        Repository $segmentedCustomersRepository,
        Repository $customerBelongingToOneLevelRepository,
        CouponUsageRepository $couponUsageRepository,
        CampaignValidator $campaignValidator,
        CampaignUsageRepository $campaignUsageRepository,
        CampaignRepository $campaignRepository
    ) {
        $this->segmentedCustomersRepository = $segmentedCustomersRepository;
        $this->customerBelongingToOneLevelRepository = $customerBelongingToOneLevelRepository;
        $this->couponUsageRepository = $couponUsageRepository;
        $this->campaignValidator = $campaignValidator;
        $this->campaignUsageRepository = $campaignUsageRepository;
        $this->campaignRepository = $campaignRepository;
    }

    /**
     * @param CustomerDetails $customer
     *
     * @return null|Campaign
     */
    public function getCashbackForCustomer(CustomerDetails $customer)
    {
        $customerSegments = $this->segmentedCustomersRepository->findBy(['customerId' => $customer->getCustomerId()->__toString()]);
        $segments = array_map(function (SegmentedCustomers $segmentedCustomers) {
            return new SegmentId($segmentedCustomers->getSegmentId()->__toString());
        }, $customerSegments);

        $availableCampaigns = $this->campaignRepository->getActiveCashbackCampaignsForLevelAndSegment(
            $segments,
            new LevelId($customer->getLevelId()->__toString())
        );

        if (!$availableCampaigns) {
            return;
        }

        /** @var Campaign $best */
        $best = null;

        /** @var Campaign $campaign */
        foreach ($availableCampaigns as $campaign) {
            if (null == $best || $campaign->getPointValue() > $best->getPointValue()) {
                $best = $campaign;
            }
        }

        return $best;
    }

    public function visibleForCustomers(Campaign $campaign)
    {
        if (!$this->campaignValidator->isCampaignVisible($campaign)) {
            return [];
        }

        // todo: check campaign limits?

        $customers = [];

        foreach ($campaign->getSegments() as $segmentId) {
            $segmented = $this->segmentedCustomersRepository->findBy(['segmentId' => $segmentId->__toString()]);
            /** @var SegmentedCustomers $segm */
            foreach ($segmented as $segm) {
                $customers[$segm->getCustomerId()->__toString()] = $segm->getCustomerId()->__toString();
            }
        }

        foreach ($campaign->getLevels() as $levelId) {
            $cst = $this->customerBelongingToOneLevelRepository->findBy(['levelId' => $levelId->__toString()]);
            /** @var CustomersBelongingToOneLevel $c */
            foreach ($cst as $c) {
                foreach ($c->getCustomers() as $cust) {
                    $customers[$cust['customerId']] = $cust['customerId'];
                }
            }
        }

        return $customers;
    }

    public function getAllCoupons(Campaign $campaign)
    {
        return array_map(function (Coupon $coupon) {
            return $coupon->getCode();
        }, $campaign->getCoupons());
    }

    public function getUsedCoupons(Campaign $campaign)
    {
        return array_map(function (CouponUsage $couponUsage) {
            return $couponUsage->getCoupon()->getCode();
        }, $this->couponUsageRepository->findByCampaign($campaign->getCampaignId()));
    }

    public function getFreeCoupons(Campaign $campaign)
    {
        return array_diff($this->getAllCoupons($campaign), $this->getUsedCoupons($campaign));
    }

    public function getUsageLeft(Campaign $campaign)
    {
        $used = $this->couponUsageRepository->countUsageForCampaign($campaign->getCampaignId());

        $usageLeft = $campaign->getLimit() - $used;
        if ($usageLeft < 0) {
            $usageLeft = 0;
        }
        $freeCoupons = $this->getCouponsUsageLeftCount($campaign);

        if ($campaign->isUnlimited()) {
            return $freeCoupons;
        } else {
            return min($freeCoupons, $usageLeft);
        }
    }

    public function getUsageLeftForCustomer(Campaign $campaign, $customerId)
    {
        $freeCoupons = $this->getCouponsUsageLeftCount($campaign);
        if (!$campaign->isSingleCoupon()) {
            $usageForCustomer = $this->couponUsageRepository->countUsageForCampaignAndCustomer(
                $campaign->getCampaignId(),
                new CustomerId($customerId)
            );
        } else {
            $campaignCoupon = $this->getAllCoupons($campaign);
            $coupon = $this->couponUsageRepository->find($campaign->getCampaignId().'_'.$customerId.'_'.reset($campaignCoupon));
            $usageForCustomer = $coupon ? $coupon->getUsage() : 0;
        }
        $usageLeftForCustomer = $campaign->getLimitPerUser() - $usageForCustomer;
        if ($usageLeftForCustomer < 0) {
            $usageLeftForCustomer = 0;
        }

        if ($campaign->isUnlimited()) {
            return $freeCoupons;
        } else {
            return min($freeCoupons, $usageLeftForCustomer);
        }
    }

    /**
     * @param Campaign $campaign
     *
     * @return int
     */
    protected function getCouponsUsageLeftCount($campaign)
    {
        if (!$campaign->isSingleCoupon()) {
            $freeCoupons = count($this->getFreeCoupons($campaign));
        } else {
            $usages = 0;
            $usagesRepo = $this->campaignUsageRepository->find($campaign->getCampaignId());
            if ($usagesRepo instanceof CampaignUsage) {
                $usages = $usagesRepo->getCampaignUsage();
            }
            if ($campaign->isUnlimited()) {
                $freeCoupons = PHP_INT_MAX;
            } else {
                $freeCoupons = ($campaign->getLimit() - $usages) < 0 ? 0 : $campaign->getLimit() - $usages;
            }
        }

        return $freeCoupons;
    }
}
