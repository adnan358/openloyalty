<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */
namespace OpenLoyalty\Bundle\CampaignBundle\Controller\Api;

use Broadway\CommandHandling\CommandBus;
use FOS\RestBundle\Controller\Annotations\Route;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use OpenLoyalty\Bundle\CampaignBundle\Exception\NotEnoughPointsException;
use OpenLoyalty\Bundle\CampaignBundle\Form\Type\CashbackRedeemFormType;
use OpenLoyalty\Bundle\CampaignBundle\Form\Type\CashbackSimulationFormType;
use OpenLoyalty\Bundle\CampaignBundle\Model\CashbackRedeem;
use OpenLoyalty\Bundle\CampaignBundle\Model\CashbackSimulation;
use OpenLoyalty\Bundle\CampaignBundle\Model\CashbackSimulationCriteria;
use OpenLoyalty\Component\Campaign\Domain\Campaign;
use OpenLoyalty\Component\Customer\Domain\CampaignId;
use OpenLoyalty\Component\Customer\Domain\Command\BuyCampaign;
use OpenLoyalty\Component\Customer\Domain\CustomerId;
use OpenLoyalty\Component\Customer\Domain\Model\Coupon;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use OpenLoyalty\Component\Campaign\Domain\CustomerId as CampaignCustomerId;

/**
 * Class CashbackController.
 */
class CashbackController extends FOSRestController
{
    /**
     * Simulate cashback.
     *
     * @Route(name="oloy.campaign.admin.cashback.simulate", path="/admin/campaign/cashback/simulate")
     * @Method("POST")
     * @Security("is_granted('CASHBACK')")
     *
     * @ApiDoc(
     *     name="simulate cashback",
     *     section="Campaign",
     *     input={"class"="OpenLoyalty\Bundle\CampaignBundle\Form\Type\CashbackSimulationFormType" ,"name"= ""}
     * )
     *
     * @param Request $request
     *
     * @return View
     */
    public function simulateAction(Request $request)
    {
        $form = $this->get('form.factory')->createNamed('', CashbackSimulationFormType::class);

        $form->handleRequest($request);
        $campaignValidator = $this->get('oloy.campaign.campaign_validator');

        if ($form->isSubmitted() && $form->isValid()) {
            $provider = $this->get('oloy.campaign.campaign_provider');
            /** @var CashbackSimulationCriteria $data */
            $data = $form->getData();
            $customer = $this->get('oloy.user.read_model.repository.customer_details')->find($data->getCustomerId());
            if (!$customer) {
                throw $this->createNotFoundException();
            }

            /** @var Campaign $cashback */
            $cashback = $provider->getCashbackForCustomer($customer);

            if (!$cashback) {
                return $this->view(['error' => 'cashback not available'], Response::HTTP_BAD_REQUEST);
            }

            try {
                $campaignValidator->hasCustomerEnoughPointsForCashback(
                    $data->getPointsAmount(),
                    new CampaignCustomerId($data->getCustomerId())
                );
            } catch (NotEnoughPointsException $e) {
                return $this->view(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }

            return $this->view(new CashbackSimulation(
                $data->getCustomerId(),
                $data->getPointsAmount(),
                $cashback->getPointValue(),
                $cashback->calculateCashbackAmount($data->getPointsAmount())
            ));
        }

        return $this->view($form->getErrors(), Response::HTTP_BAD_REQUEST);
    }

    /**
     * Redeem cashback.
     *
     * @Route(name="oloy.campaign.admin.cashback.redeem", path="/admin/campaign/cashback/redeem")
     * @Method("POST")
     * @Security("is_granted('CASHBACK')")
     *
     * @ApiDoc(
     *     name="redeem cashback",
     *     section="Campaign",
     *     input={"class"="OpenLoyalty\Bundle\CampaignBundle\Form\Type\CashbackRedeemFormType" ,"name"= ""}
     * )
     *
     * @param Request $request
     *
     * @return View
     */
    public function redeemAction(Request $request)
    {
        $form = $this->get('form.factory')->createNamed('', CashbackRedeemFormType::class);

        $form->handleRequest($request);
        $campaignValidator = $this->get('oloy.campaign.campaign_validator');

        if ($form->isSubmitted() && $form->isValid()) {
            $provider = $this->get('oloy.campaign.campaign_provider');
            /** @var CashbackRedeem $data */
            $data = $form->getData();
            $customer = $this->get('oloy.user.read_model.repository.customer_details')->find($data->getCustomerId());
            if (!$customer) {
                throw $this->createNotFoundException();
            }

            /** @var Campaign $cashback */
            $cashback = $provider->getCashbackForCustomer($customer);

            if (!$cashback) {
                return $this->view(['error' => 'cashback not available'], Response::HTTP_BAD_REQUEST);
            }

            if (!$this->isCashbackValid($data, $cashback)) {
                return $this->view(['error' => 'cashback not valid'], Response::HTTP_BAD_REQUEST);
            }

            try {
                $campaignValidator->hasCustomerEnoughPointsForCashback(
                    $data->getPointsAmount(),
                    new CampaignCustomerId($data->getCustomerId())
                );
            } catch (NotEnoughPointsException $e) {
                return $this->view(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }

            /** @var CommandBus $bus */
            $bus = $this->get('broadway.command_handling.command_bus');
            $bus->dispatch(
                new BuyCampaign(
                    new CustomerId($data->getCustomerId()),
                    new CampaignId($cashback->getCampaignId()->__toString()),
                    $cashback->getName(),
                    $data->getPointsAmount(),
                    new Coupon(''),
                    $cashback->getReward()
                )
            );

            return $this->view($data);
        }

        return $this->view($form->getErrors(), Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param CashbackRedeem $cashbackRedeem
     * @param Campaign       $campaign
     *
     * @return bool
     */
    private function isCashbackValid(CashbackRedeem $cashbackRedeem, Campaign $campaign)
    {
        if ($campaign->getPointValue() != $cashbackRedeem->getPointValue()) {
            return false;
        }
        if ($campaign->calculateCashbackAmount($cashbackRedeem->getPointsAmount()) != $cashbackRedeem->getRewardAmount()) {
            return false;
        }

        return true;
    }
}
